<?php

namespace App\Support;

/**
 * Utilidades para el NIT colombiano.
 */
final class Nit
{
    /** Pesos oficiales de la DIAN, aplicados desde el dígito más a la derecha. */
    private const PESOS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

    /** Deja solo los dígitos del NIT (sin puntos, guiones ni DV). */
    public static function limpiar(?string $nit): string
    {
        return preg_replace('/\D/', '', (string) $nit) ?? '';
    }

    /** Calcula el dígito de verificación según el algoritmo de la DIAN. Devuelve null si el NIT no es válido. */
    public static function digitoVerificacion(?string $nit): ?int
    {
        $digitos = self::limpiar($nit);

        if ($digitos === '' || strlen($digitos) > count(self::PESOS)) {
            return null;
        }

        $suma = 0;
        foreach (array_reverse(str_split($digitos)) as $i => $digito) {
            $suma += ((int) $digito) * self::PESOS[$i];
        }

        $residuo = $suma % 11;

        return $residuo > 1 ? 11 - $residuo : $residuo;
    }

    /** Formato legible: 800.197.268-4 */
    public static function formatear(?string $nit, ?int $dv = null): string
    {
        $digitos = self::limpiar($nit);
        if ($digitos === '') {
            return '';
        }

        $dv ??= self::digitoVerificacion($digitos);

        return number_format((int) $digitos, 0, ',', '.').($dv !== null ? "-{$dv}" : '');
    }
}
