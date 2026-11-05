<?php

namespace App\Actions\Contratos;

use App\Enums\EstadoFirma;
use App\Mail\CorreoFirmaContrato;
use App\Models\CodigoFirma;
use App\Models\DocumentoContrato;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Mensajeria\Mensajero;
use App\Support\FirmaManuscrita;
use App\Support\Pdf\PdfContrato;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Firma electrónica del contratante: valida el código de un solo uso, guarda la firma dibujada,
 * genera el PDF firmado con el certificado de evidencias y registra su huella (SHA-256).
 * Después envía la copia por correo y avisa a quien emitió el contrato.
 */
class FirmarDocumentoContrato
{
    public function __invoke(
        DocumentoContrato $documento,
        string $codigo,
        ?string $firmaDataUri,
        bool $acepto,
        ?string $ip = null,
        ?string $userAgent = null,
    ): DocumentoContrato {
        if (! $documento->admiteFirma()) {
            throw ValidationException::withMessages(['codigo' => 'Este enlace ya no permite firmar.']);
        }

        if (! $acepto) {
            throw ValidationException::withMessages(['acepto' => 'Debe aceptar firmar electrónicamente el contrato.']);
        }

        $png = FirmaManuscrita::desdeDataUri($firmaDataUri);
        $this->validarCodigo($documento, $codigo, $ip, $userAgent);

        $documento = DB::transaction(function () use ($documento, $png, $ip, $userAgent): DocumentoContrato {
            /** @var DocumentoContrato $doc */
            $doc = DocumentoContrato::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($documento->id);

            if (! $doc->admiteFirma()) {
                throw ValidationException::withMessages(['codigo' => 'Este enlace ya no permite firmar.']);
            }

            /** @var CodigoFirma $usado */
            $usado = $doc->codigos()->whereNull('usado_at')->latest('id')->firstOrFail();
            $usado->update(['usado_at' => now()]);

            $carpeta = "documentos-contrato/{$doc->empresa_id}/{$doc->id}";
            Storage::disk(DocumentoContrato::DISCO)->put("{$carpeta}/firma-contratante.png", $png);

            $doc->forceFill([
                'estado' => EstadoFirma::Firmado,
                'firmado_at' => now(),
                'firma_path' => "{$carpeta}/firma-contratante.png",
                'firma_ip' => $ip,
                'firma_user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
                'firma_canal_codigo' => $usado->canal,
                'firma_destino_codigo' => $usado->destino,
            ])->save();

            $doc->registrar('firmado', ['código' => ($usado->canal === 'sms' ? 'SMS ' : 'correo ').$usado->destino], $ip, $userAgent);

            $pdf = PdfContrato::documento($doc->fresh(['eventos']));
            Storage::disk(DocumentoContrato::DISCO)->put("{$carpeta}/contrato-firmado.pdf", $pdf);

            $doc->forceFill([
                'pdf_firmado_path' => "{$carpeta}/contrato-firmado.pdf",
                'huella_pdf' => hash('sha256', $pdf),
            ])->save();

            return $doc;
        });

        $this->avisar($documento);

        return $documento;
    }

    private function validarCodigo(DocumentoContrato $documento, string $codigo, ?string $ip, ?string $userAgent): void
    {
        /** @var CodigoFirma|null $vigente */
        $vigente = $documento->codigos()->whereNull('usado_at')->latest('id')->first();

        if (! $vigente || ! $vigente->disponible()) {
            throw ValidationException::withMessages(['codigo' => 'El código venció o ya no es válido. Solicite uno nuevo.']);
        }

        if ($vigente->coincide($codigo)) {
            return;
        }

        $vigente->increment('intentos');
        $restantes = CodigoFirma::MAX_INTENTOS - $vigente->intentos;
        $documento->registrar('codigo_fallido', ['intento' => $vigente->intentos], $ip, $userAgent);

        if ($restantes <= 0) {
            $documento->registrar('codigo_bloqueado', [], $ip, $userAgent);

            throw ValidationException::withMessages(['codigo' => 'Código incorrecto. Por seguridad lo bloqueamos: solicite uno nuevo.']);
        }

        throw ValidationException::withMessages([
            'codigo' => 'Código incorrecto. Le '.($restantes === 1 ? 'queda 1 intento.' : "quedan {$restantes} intentos."),
        ]);
    }

    /** Copia por correo al firmante y aviso a quien emitió el documento. No interrumpe la firma si fallan. */
    private function avisar(DocumentoContrato $documento): void
    {
        $numero = $documento->datos['valores']['contrato.numero'] ?? '';
        $empresa = Empresa::find($documento->empresa_id);

        try {
            if ($documento->firmante_email) {
                $nombreEmpresa = $documento->datos['empresa']['nombre_comercial'] ?: $documento->datos['empresa']['razon_social'];

                Mensajero::correo($empresa, 'contrato_firmado', $documento->firmante_email, new CorreoFirmaContrato(
                    CorreoFirmaContrato::FIRMADO,
                    $nombreEmpresa,
                    $numero,
                    (string) $documento->firmante_nombre,
                    enlace: $documento->enlaceFirma(),
                    pdf: base64_encode(Storage::disk(DocumentoContrato::DISCO)->get($documento->pdf_firmado_path)),
                    nombreArchivo: $documento->nombreArchivo(),
                ), $documento);
            }

            if ($usuario = User::find($documento->user_id)) {
                Notification::make()
                    ->success()
                    ->title("Contrato {$numero} firmado")
                    ->body("{$documento->firmante_nombre} firmó el contrato el {$documento->firmado_at->format('d/m/Y h:i a')}.")
                    ->sendToDatabase($usuario);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
