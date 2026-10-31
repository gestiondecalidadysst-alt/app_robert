<?php

namespace App\Actions\Contratos;

use App\Enums\EstadoFirma;
use App\Models\Contrato;
use App\Models\DocumentoContrato;
use App\Models\Empresa;
use App\Support\Contratos\RedactorContrato;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Emite el documento del contrato: diligencia la plantilla vigente con los datos del contrato y
 * congela el resultado (texto, datos, logo, pie y firma del representante). Si falta algún dato,
 * no emite y dice cuál falta.
 *
 * Si la empresa activó el envío automático, lo envía para firma con los datos del cliente.
 */
class EmitirDocumentoContrato
{
    public function __invoke(Contrato $contrato): DocumentoContrato
    {
        $empresa = Empresa::findOrFail($contrato->empresa_id);
        $faltantes = $this->revisar($contrato, $empresa);

        if ($faltantes !== []) {
            throw ValidationException::withMessages(['documento' => $faltantes]);
        }

        $plantilla = $empresa->plantillaContratoVigente();
        $redaccion = RedactorContrato::componer($contrato, $plantilla, $empresa);

        $documento = DB::transaction(function () use ($contrato, $empresa, $plantilla, $redaccion): DocumentoContrato {
            Contrato::query()->deEmpresa($empresa)->whereKey($contrato->getKey())->lockForUpdate()->first();

            if ($contrato->documentos()->activos()->exists()) {
                throw ValidationException::withMessages(['documento' => ['El contrato ya tiene un documento emitido. Anúlelo para emitir otro.']]);
            }

            $documento = new DocumentoContrato([
                'contrato_id' => $contrato->id,
                'plantilla_contrato_id' => $plantilla->id,
                'version_plantilla' => $plantilla->version,
                'codigo_formato' => $plantilla->codigo_formato,
                'contenido' => $redaccion['html'],
                'datos' => [],
                'huella_contenido' => '',
                'estado' => EstadoFirma::Emitido,
                'codigo_verificacion' => Str::lower(Str::random(24)),
                'user_id' => auth()->id(),
            ]);
            $documento->empresa_id = $empresa->id;
            $documento->save();

            $carpeta = "documentos-contrato/{$empresa->id}/{$documento->id}";
            $archivos = [
                'logo' => $this->copiar($empresa->logo_path, 'public', "{$carpeta}/logo"),
                'pie' => $this->copiar($empresa->pie_documentos_path, 'public', "{$carpeta}/pie"),
            ];
            $firmaContratista = $this->copiar($empresa->firma_representante_path, DocumentoContrato::DISCO, "{$carpeta}/firma-contratista");

            $datos = [
                'valores' => $redaccion['valores'] + ['contrato.numero' => $contrato->numero_formateado],
                'empresa' => RedactorContrato::datosEmpresa($empresa, $plantilla),
                'archivos' => $archivos,
            ];

            $documento->forceFill([
                'datos' => $datos,
                'firma_contratista_path' => $firmaContratista,
                'huella_contenido' => hash('sha256', $documento->contenido . json_encode($datos, JSON_UNESCAPED_UNICODE)),
            ])->save();

            $documento->registrar('emitido', ['plantilla' => 'versión ' . $plantilla->version_formateada]);

            return $documento;
        });

        if ($empresa->firma_envio_automatico) {
            try {
                app(EnviarParaFirma::class)($documento, EnviarParaFirma::firmantePorDefecto($contrato));
            } catch (Throwable) {
                // Sin datos de contacto válidos: queda emitido y se envía a mano.
            }
        }

        return $documento->refresh();
    }

    /**
     * Qué impide emitir (vacío = se puede). La pantalla lo muestra antes de emitir.
     *
     * @return list<string>
     */
    public function revisar(Contrato $contrato, Empresa $empresa): array
    {
        if ($contrato->anulado()) {
            return ['El contrato está anulado.'];
        }

        if ($contrato->documentos()->activos()->exists()) {
            return ['El contrato ya tiene un documento emitido. Anúlelo para emitir otro.'];
        }

        $plantilla = $empresa->plantillaContratoVigente();
        if (! $plantilla) {
            return ['La empresa no tiene plantilla de contrato (Configuración → Plantilla de contrato).'];
        }

        $faltantes = RedactorContrato::componer($contrato, $plantilla, $empresa)['faltantes'];

        if (blank($empresa->representante_legal) || blank($empresa->representante_documento)) {
            $faltantes[] = 'Mi empresa · Nombre y documento del representante legal (van en el bloque de firmas)';
        }

        return array_values(array_unique($faltantes));
    }

    /** Copia un archivo de la empresa a la carpeta del documento (para que no cambie después). */
    private function copiar(?string $ruta, string $discoOrigen, string $destinoSinExtension): ?string
    {
        if (blank($ruta) || ! Storage::disk($discoOrigen)->exists($ruta)) {
            return null;
        }

        $extension = pathinfo($ruta, PATHINFO_EXTENSION) ?: 'png';
        $destino = "{$destinoSinExtension}.{$extension}";

        Storage::disk(DocumentoContrato::DISCO)->put($destino, Storage::disk($discoOrigen)->get($ruta));

        return $destino;
    }
}
