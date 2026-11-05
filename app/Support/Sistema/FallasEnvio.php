<?php

namespace App\Support\Sistema;

use App\Models\Empresa;
use App\Models\EnvioMensaje;
use App\Support\Avisos;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Fallas de SMS y correo que NO son errores del sistema sino de la cuenta del proveedor o del dato:
 * sin créditos, IP no autorizada, credenciales, número o correo inválido, límite del mes.
 * - No se reintentan (no se arreglan solas) ni se registran como error del sistema.
 * - Se avisa a quien administra Integraciones de la empresa (una vez al día por tipo de falla).
 * Las demás (red caída, el proveedor no responde) sí se reintentan.
 */
final class FallasEnvio
{
    private const PERMANENTES = [
        'credito' => 'La cuenta de SMS (Onurix) no tiene créditos. Recárguela para que salgan los mensajes.',
        'ip' => 'Onurix no tiene autorizada la IP del servidor. Autorícela (Integraciones → «Ver IP del servidor»).',
        'credencial' => 'Las credenciales de Onurix no son válidas. Revíselas en Integraciones.',
        'limite' => 'Se alcanzó el límite de SMS del mes configurado en Integraciones.',
        'destino' => null, // número o correo inválido: es del dato, no de la cuenta (sin aviso)
    ];

    /** Tipo de falla permanente, o null si vale la pena reintentar. */
    public static function permanente(?string $error): ?string
    {
        $e = Str::lower(Str::ascii((string) $error));

        return match (true) {
            $e === '' => null,
            str_contains($e, 'credito') => 'credito',
            str_contains($e, "ip's no permitidas") || str_contains($e, 'ip no permitida') || str_contains($e, 'ips no permitidas') => 'ip',
            str_contains($e, 'credencial') || str_contains($e, 'client') && str_contains($e, 'invalid') || str_contains($e, 'autentic') || str_contains($e, 'unauthorized') => 'credencial',
            str_contains($e, 'limite') => 'limite',
            str_contains($e, 'no valido') || str_contains($e, 'invalido') || str_contains($e, 'invalid address') => 'destino',
            default => null,
        };
    }

    /** Avisa a la empresa (campana de quien puede ver Integraciones), máximo una vez al día por tipo. */
    public static function avisar(EnvioMensaje $envio, string $tipo): void
    {
        $mensaje = self::PERMANENTES[$tipo] ?? null;
        if (! $mensaje || ! Cache::add("falla-envio.{$envio->empresa_id}.{$tipo}", true, now()->addDay())) {
            return;
        }

        $empresa = Empresa::find($envio->empresa_id);
        if ($empresa) {
            Avisos::aQuienPueda($empresa, 'ver_integraciones', Notification::make()->warning()
                ->title($envio->canal === 'sms' ? 'Los SMS no se están enviando' : 'Los correos no se están enviando')
                ->body($mensaje.' Detalle: '.Str::limit((string) $envio->error, 160)));
        }
    }
}
