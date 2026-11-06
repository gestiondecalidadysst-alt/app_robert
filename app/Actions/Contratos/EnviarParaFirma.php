<?php

namespace App\Actions\Contratos;

use App\Enums\EstadoFirma;
use App\Mail\CorreoFirmaContrato;
use App\Models\Contrato;
use App\Models\DocumentoContrato;
use App\Models\Empresa;
use App\Services\Mensajeria\Mensajero;
use App\Support\Contacto;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Envía (o reenvía) el enlace de firma al cliente: por SMS si hay celular y SMS configurado,
 * y por correo si hay correo. Si el enlace sigue vigente, reenvía el mismo y amplía su vigencia.
 *
 * También se usa para los recordatorios automáticos (tipo recordatorio_firma).
 */
class EnviarParaFirma
{
    /**
     * @param  array{nombre?: ?string, documento?: ?string, celular?: ?string, email?: ?string}  $firmante
     * @return array{enlace: string, canales: list<string>, omitidos: list<string>}
     */
    public function __invoke(DocumentoContrato $documento, array $firmante, bool $recordatorio = false): array
    {
        if ($documento->anulado() || $documento->firmado()) {
            throw ValidationException::withMessages(['firmante' => 'El documento ya está '.mb_strtolower($documento->estado->getLabel()).'.']);
        }

        $empresa = Empresa::findOrFail($documento->empresa_id);
        $nombre = trim((string) ($firmante['nombre'] ?? ''));
        $celular = filled($firmante['celular'] ?? null) ? Contacto::celular($firmante['celular']) : null;
        $email = filled($firmante['email'] ?? null) ? trim($firmante['email']) : null;

        $errores = [];
        if ($nombre === '') {
            $errores['nombre'] = 'Indique el nombre de quien firma.';
        }
        if (filled($firmante['celular'] ?? null) && ! $celular) {
            $errores['celular'] = 'El celular debe tener 10 dígitos y empezar por 3.';
        }
        if ($email !== null && ! Contacto::correoValido($email)) {
            $errores['email'] = 'El correo no es válido.';
        }
        $smsDisponible = $celular && Mensajero::motivoSinSms($empresa) === null;
        if (! $smsDisponible && ! $email && ! isset($errores['celular'])) {
            $errores['celular'] = $celular
                ? Mensajero::motivoSinSms($empresa).' Registre un correo para enviar el enlace.'
                : 'Registre un celular o un correo para enviar el enlace.';
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        // Mismo enlace si sigue vigente; si no, uno nuevo.
        $token = $documento->token_cifrado && $documento->token_expira_at?->isFuture()
            ? $documento->token_cifrado
            : Str::random(22);

        $documento->forceFill([
            'firmante_nombre' => $nombre,
            'firmante_documento' => filled($firmante['documento'] ?? null) ? trim($firmante['documento']) : null,
            'firmante_celular' => $celular,
            'firmante_email' => $email,
            'token_hash' => DocumentoContrato::huellaToken($token),
            'token_cifrado' => $token,
            // El recordatorio no amplía el plazo; un envío manual sí lo renueva.
            'token_expira_at' => $recordatorio && $documento->token_expira_at
                ? $documento->token_expira_at
                : now()->addDays(max(1, (int) $empresa->firma_dias_enlace))->endOfDay(),
            'estado' => $documento->estado === EstadoFirma::Visto ? EstadoFirma::Visto : EstadoFirma::Enviado,
            'enviado_at' => $documento->enviado_at ?? now(),
            'recordatorio_enviado_at' => $recordatorio ? now() : $documento->recordatorio_enviado_at,
        ])->save();

        $enlace = $documento->enlaceFirma();
        $numero = $documento->datos['valores']['contrato.numero'] ?? '';
        $nombreEmpresa = $documento->datos['empresa']['nombre_comercial'] ?: $documento->datos['empresa']['razon_social'];
        $vence = $documento->token_expira_at->format('d/m/Y');
        $tipo = $recordatorio ? 'recordatorio_firma' : 'enlace_firma';
        $canales = [];
        $omitidos = [];

        if ($celular) {
            $texto = self::textoSms($nombreEmpresa, $numero, $enlace, $documento->token_expira_at->format('d/m'), $recordatorio);
            // El envío manual va de inmediato (quien envía ve si Onurix lo aceptó); el recordatorio, en cola.
            $envio = Mensajero::sms($empresa, $tipo, $celular, $texto, $documento, ahora: ! $recordatorio);
            in_array($envio->estado, ['omitido', 'fallido'], true)
                ? $omitidos[] = 'SMS no enviado: '.$envio->error
                : $canales[] = 'SMS '.Contacto::ocultarCelular($celular);
        }

        if ($email) {
            // Igual que el SMS de arriba: el envío manual va de inmediato (quien envía ve
            // si salió), el recordatorio automático va en cola. Antes el correo siempre se
            // encolaba, así que si el worker no procesaba la cola el enlace de firma no
            // llegaba aunque el recibo (que es inmediato) sí.
            $envio = Mensajero::correo($empresa, $tipo, $email, new CorreoFirmaContrato(
                $recordatorio ? CorreoFirmaContrato::RECORDATORIO : CorreoFirmaContrato::ENLACE,
                $nombreEmpresa,
                $numero,
                $nombre,
                enlace: $enlace,
                vence: $vence,
            ), $documento, ahora: ! $recordatorio);

            in_array($envio->estado, ['omitido', 'fallido'], true)
                ? $omitidos[] = 'Correo no enviado: '.$envio->error
                : $canales[] = 'correo '.Contacto::ocultarCorreo($email);
        }

        // Lo omitido (p. ej. SMS sin configurar) queda en el historial de mensajes, no en las evidencias.
        $documento->registrar($recordatorio ? 'recordatorio' : 'enviado', array_filter([
            'canales' => $canales,
            'vence' => $vence,
        ]));

        return ['enlace' => $enlace, 'canales' => $canales, 'omitidos' => $omitidos];
    }

    /** Datos del firmante sugeridos a partir del cliente del contrato. */
    public static function firmantePorDefecto(Contrato $contrato): array
    {
        $contrato->loadMissing('cliente');
        $cliente = $contrato->cliente;
        $esEmpresa = $cliente?->dv !== null;

        return [
            'nombre' => $esEmpresa ? ($cliente?->contacto ?: $contrato->responsable_nombre ?: $cliente?->nombre) : $cliente?->nombre,
            'documento' => $esEmpresa ? $contrato->responsable_documento : $cliente?->documento,
            'celular' => Contacto::celular($cliente?->telefono) ?? Contacto::celular($contrato->responsable_telefono),
            'email' => $cliente?->email,
        ];
    }

    /** SMS de un solo segmento (160 caracteres, sin tildes). */
    public static function textoSms(string $empresa, string $numero, string $enlace, string $vence, bool $recordatorio = false): string
    {
        $empresa = Str::limit(Contacto::textoSms($empresa), 24, '');
        $accion = $recordatorio ? 'Recuerde firmar' : 'Firme';

        $texto = "{$empresa}: {$accion} su contrato No {$numero} aqui: {$enlace} Vence {$vence}";

        return strlen($texto) <= 160 ? $texto : "{$empresa}: firme el contrato {$numero}: {$enlace}";
    }
}
