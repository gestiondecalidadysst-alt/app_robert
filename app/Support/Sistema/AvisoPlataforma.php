<?php

namespace App\Support\Sistema;

use App\Jobs\EnviarAvisoPlataforma;
use Illuminate\Support\Str;

/**
 * Avisos de plataforma hacia el webhook (ALERTAS_WEBHOOK_URL): errores inesperados y servicios
 * caídos. Nunca interrumpe lo que esté pasando: si no hay webhook configurado o falla el
 * encolado, sigue de largo.
 */
final class AvisoPlataforma
{
    public const ERROR = 'sistema.error';

    public const SALUD = 'sistema.salud';

    /** @param array<string, mixed> $datos */
    public static function enviar(string $evento, string $descripcion, array $datos = []): void
    {
        if (blank(config('ruta.alertas_webhook_url'))) {
            return;
        }

        rescue(fn () => EnviarAvisoPlataforma::dispatch($evento, [
            'id' => (string) Str::uuid(),
            'evento' => $evento,
            'descripcion' => $descripcion,
            'fecha' => now()->toIso8601String(),
            'datos' => $datos,
        ]), report: false);
    }
}
