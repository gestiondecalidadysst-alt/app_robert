<?php

namespace App\Support\Sistema;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * «Latidos» de los procesos de fondo: el worker de la cola marca el suyo en cada vuelta (máximo una
 * escritura cada 30 s) y las tareas programadas cada minuto. Si un latido se queda viejo, el proceso
 * está caído (lo muestra «Estado del sistema» y lo avisa el vigilante).
 */
final class Latidos
{
    public const WORKER = 'worker';

    public const PROGRAMADAS = 'programadas';

    /** Minutos sin latido para considerar caído el proceso. */
    public const MINUTOS_CAIDO = 10;

    private static array $ultimaEscritura = [];

    public static function marcar(string $proceso, bool $siempre = false): void
    {
        $ahora = time();
        if (! $siempre && ($ahora - (self::$ultimaEscritura[$proceso] ?? 0)) < 30) {
            return;
        }

        try {
            Cache::forever("latido.{$proceso}", $ahora);
            self::$ultimaEscritura[$proceso] = $ahora;
        } catch (Throwable) {
            // Sin caché disponible: no se interrumpe el proceso.
        }
    }

    public static function ultimo(string $proceso): ?Carbon
    {
        try {
            $t = Cache::get("latido.{$proceso}");
        } catch (Throwable) {
            return null;
        }

        return $t ? Carbon::createFromTimestamp($t)->timezone(config('app.timezone')) : null;
    }

    public static function vivo(string $proceso): bool
    {
        $ultimo = self::ultimo($proceso);

        return $ultimo !== null && $ultimo->gt(now()->subMinutes(self::MINUTOS_CAIDO));
    }
}
