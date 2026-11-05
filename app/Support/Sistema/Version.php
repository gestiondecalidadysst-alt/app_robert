<?php

namespace App\Support\Sistema;

use Illuminate\Support\Facades\Cache;

/**
 * Versión del código que está corriendo, para saber si un despliegue ya llegó al servidor.
 *
 * Se lee del commit de git (o de la variable `APP_VERSION`, útil cuando la imagen
 * se construye sin el directorio `.git`).
 */
final class Version
{
    public static function commit(): string
    {
        return Cache::remember('sistema.version.commit', now()->addMinutes(10), function (): string {
            if ($version = config('app.version')) {
                return (string) $version;
            }

            $cabeza = base_path('.git/HEAD');

            if (! is_file($cabeza)) {
                return 'desconocida';
            }

            $contenido = trim((string) file_get_contents($cabeza));

            if (str_starts_with($contenido, 'ref: ')) {
                $ref = base_path('.git/'.substr($contenido, 5));
                $contenido = is_file($ref) ? trim((string) file_get_contents($ref)) : '';
            }

            return $contenido === '' ? 'desconocida' : substr($contenido, 0, 7);
        });
    }

    /** Fecha del despliegue: la del último fichero compilado, que cambia en cada build. */
    public static function desplegadoEl(): ?string
    {
        $marca = base_path('composer.lock');

        return is_file($marca) ? date('d/m/Y H:i', (int) filemtime($marca)) : null;
    }

    public static function resumen(): string
    {
        $fecha = self::desplegadoEl();

        return 'Versión '.self::commit().($fecha ? ' · desplegada el '.$fecha : '');
    }
}
