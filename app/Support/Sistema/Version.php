<?php

namespace App\Support\Sistema;

/**
 * Versión del código que está corriendo, para saber si un despliegue ya llegó al servidor.
 *
 * La imagen no incluye `.git`, así que la referencia se graba al construir
 * (`.build` y `.version`, creados en el Dockerfile). En local se lee de `.git`.
 * No se cachea a propósito: justo cuando hace falta, un valor viejo engaña.
 */
final class Version
{
    public static function commit(): string
    {
        if ($version = config('app.version')) {
            return (string) $version;
        }

        if ($grabada = self::leer('.version')) {
            return $grabada;
        }

        return self::desdeGit() ?? 'desconocida';
    }

    /** Fecha en que se construyó la imagen que está corriendo. */
    public static function desplegadoEl(): ?string
    {
        return self::leer('.build');
    }

    public static function resumen(): string
    {
        $fecha = self::desplegadoEl();

        return 'Versión '.self::commit().($fecha ? ' · '.$fecha : '');
    }

    private static function leer(string $archivo): ?string
    {
        $ruta = base_path($archivo);

        if (! is_file($ruta)) {
            return null;
        }

        $contenido = trim((string) file_get_contents($ruta));

        return ($contenido === '' || $contenido === 'desconocida') ? null : $contenido;
    }

    private static function desdeGit(): ?string
    {
        $cabeza = base_path('.git/HEAD');

        if (! is_file($cabeza)) {
            return null;
        }

        $contenido = trim((string) file_get_contents($cabeza));

        if (str_starts_with($contenido, 'ref: ')) {
            $ref = base_path('.git/'.substr($contenido, 5));
            $contenido = is_file($ref) ? trim((string) file_get_contents($ref)) : '';
        }

        return $contenido === '' ? null : substr($contenido, 0, 7);
    }
}
