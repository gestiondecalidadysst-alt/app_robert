<?php

namespace App\Services\Mensajeria;

use App\Jobs\EnviarCorreo;
use App\Jobs\EnviarSms;
use App\Models\DocumentoContrato;
use App\Models\Empresa;
use App\Models\EnvioMensaje;
use App\Support\Contacto;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Punto único para enviar mensajes (SMS y correo) y dejar el historial en envios_mensaje.
 *
 * - "Ahora" (sincrónico): para el código de verificación, así el cliente sabe al instante si falló.
 * - "En cola" (worker): para enlaces, recordatorios y avisos, con reintentos.
 */
final class Mensajero
{
    /** Motivo por el que la empresa no puede enviar SMS ahora (null = puede). */
    public static function motivoSinSms(Empresa $empresa): ?string
    {
        if (! $empresa->smsConfigurado()) {
            return 'El envío de SMS no está configurado (Configuración → Integraciones).';
        }

        if ($empresa->sms_limite_mensual !== null
            && self::smsDelMes($empresa) >= $empresa->sms_limite_mensual) {
            return "Se alcanzó el límite de {$empresa->sms_limite_mensual} SMS de este mes.";
        }

        return null;
    }

    public static function smsDelMes(Empresa $empresa): int
    {
        return EnvioMensaje::query()->deEmpresa($empresa)
            ->where('canal', 'sms')
            ->where('estado', 'enviado')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    public static function sms(
        Empresa $empresa,
        string $tipo,
        string $celular,
        string $texto,
        ?DocumentoContrato $documento = null,
        bool $ahora = false,
    ): EnvioMensaje {
        $envio = self::registrar($empresa, $tipo, 'sms', Contacto::ocultarCelular($celular), $documento);
        $numero = Contacto::celular($celular);

        if (! $numero) {
            return self::omitir($envio, 'Número de celular no válido.');
        }

        if ($motivo = self::motivoSinSms($empresa)) {
            return self::omitir($envio, $motivo);
        }

        $texto = Contacto::textoSms($texto);

        if ($ahora) {
            self::enviarSmsAhora($envio, $numero, $texto);
        } else {
            EnviarSms::dispatch($envio->id, $numero, $texto);
        }

        return $envio->refresh();
    }

    public static function correo(
        Empresa $empresa,
        string $tipo,
        string $correo,
        Mailable $mensaje,
        ?DocumentoContrato $documento = null,
        bool $ahora = false,
    ): EnvioMensaje {
        $envio = self::registrar($empresa, $tipo, 'correo', Contacto::ocultarCorreo($correo), $documento);

        if (! Contacto::correoValido($correo)) {
            return self::omitir($envio, 'Correo no válido.');
        }

        if ($ahora) {
            self::enviarCorreoAhora($envio, $correo, $mensaje);
        } else {
            EnviarCorreo::dispatch($envio->id, $correo, $mensaje);
        }

        return $envio->refresh();
    }

    /** Envía y actualiza el historial. Lo usan el envío inmediato y el job. */
    public static function enviarSmsAhora(EnvioMensaje $envio, string $celular, string $texto): bool
    {
        $empresa = Empresa::findOrFail($envio->empresa_id);
        $envio->increment('intentos');

        try {
            $resultado = (new OnurixSms((string) $empresa->onurix_cliente, (string) $empresa->onurix_clave))->enviar($celular, $texto);
        } catch (Throwable $e) {
            $resultado = new ResultadoSms(false, error: $e->getMessage());
        }

        $envio->forceFill([
            'proveedor' => 'onurix',
            'estado' => $resultado->exito ? 'enviado' : 'fallido',
            'referencia_proveedor' => $resultado->referencia,
            'error' => $resultado->error ? mb_substr($resultado->error, 0, 500) : null,
            'enviado_at' => $resultado->exito ? now() : null,
        ])->save();

        self::avisarSiEsDeLaCuenta($envio);

        return $resultado->exito;
    }

    public static function enviarCorreoAhora(EnvioMensaje $envio, string $correo, Mailable $mensaje): bool
    {
        $envio->increment('intentos');

        try {
            Mail::to($correo)->send($mensaje);
            $envio->forceFill(['proveedor' => config('mail.default'), 'estado' => 'enviado', 'enviado_at' => now(), 'error' => null])->save();

            return true;
        } catch (Throwable $e) {
            $envio->forceFill(['proveedor' => config('mail.default'), 'estado' => 'fallido', 'error' => mb_substr($e->getMessage(), 0, 500)])->save();
            self::avisarSiEsDeLaCuenta($envio);

            return false;
        }
    }

    /** Falla de la cuenta del proveedor (sin créditos, IP, credenciales): aviso a la empresa, una vez al día. */
    private static function avisarSiEsDeLaCuenta(EnvioMensaje $envio): void
    {
        if ($envio->estado === 'fallido' && ($tipo = \App\Support\Sistema\FallasEnvio::permanente($envio->error))) {
            rescue(fn () => \App\Support\Sistema\FallasEnvio::avisar($envio, $tipo), report: false);
        }
    }

    private static function registrar(Empresa $empresa, string $tipo, string $canal, string $destino, ?DocumentoContrato $documento): EnvioMensaje
    {
        $envio = new EnvioMensaje([
            'tipo' => $tipo,
            'canal' => $canal,
            'destino' => $destino,
            'documento_contrato_id' => $documento?->id,
        ]);
        $envio->empresa_id = $empresa->id;
        $envio->save();

        return $envio;
    }

    private static function omitir(EnvioMensaje $envio, string $motivo): EnvioMensaje
    {
        $envio->forceFill(['estado' => 'omitido', 'error' => $motivo])->save();

        return $envio;
    }
}
