<?php

namespace App\Support\Sistema;

use Illuminate\Support\Str;

/**
 * Código de referencia del error de la petición actual (REF-7F3A9C). Se muestra en la página de
 * error y queda en el log y en «Estado del sistema», para encontrar el error que reporta un usuario.
 */
final class Referencia
{
    private static ?string $actual = null;

    public static function actual(): string
    {
        return self::$actual ??= 'REF-'.Str::upper(Str::random(6));
    }

    /** Nueva referencia (cada trabajo de la cola es independiente). */
    public static function reiniciar(): void
    {
        self::$actual = null;
    }
}
