<?php

namespace App\Support;

/**
 * Convierte valores a letras en español de Colombia, para contratos:
 * 18.000.000 → "DIECIOCHO MILLONES DE PESOS M/CTE".
 */
final class NumeroEnLetras
{
    private const HASTA_29 = [
        'cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
        'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve',
    ];

    private const DECENAS = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];

    private const CENTENAS = [
        1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos',
        6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos',
    ];

    /** "DIECIOCHO MILLONES DE PESOS M/CTE" (sin centavos). */
    public static function pesos(int|float|string $valor): string
    {
        $n = (int) round((float) $valor);

        if ($n < 0) {
            throw new \InvalidArgumentException('El valor no puede ser negativo.');
        }

        $texto = match (true) {
            $n === 1 => 'un peso',
            $n >= 1_000_000 && $n % 1_000_000 === 0 => self::numero($n, true).' de pesos',
            default => self::numero($n, true).' pesos',
        };

        return mb_strtoupper($texto).' M/CTE';
    }

    /**
     * Número en letras (minúsculas). Con $apocope, "uno" final se escribe "un"
     * (para ir antes de un sustantivo: "veintiún pesos").
     */
    public static function numero(int $n, bool $apocope = false): string
    {
        if ($n === 0) {
            return 'cero';
        }

        if ($n >= 1_000_000_000_000) {
            throw new \InvalidArgumentException('Valor demasiado grande.');
        }

        if ($n >= 1_000_000) {
            $millones = intdiv($n, 1_000_000);
            $resto = $n % 1_000_000;
            $texto = $millones === 1 ? 'un millón' : self::numero($millones, true).' millones';

            return $resto ? $texto.' '.self::numero($resto, $apocope) : $texto;
        }

        if ($n >= 1000) {
            $miles = intdiv($n, 1000);
            $resto = $n % 1000;
            $texto = $miles === 1 ? 'mil' : self::tresDigitos($miles, true).' mil';

            return $resto ? $texto.' '.self::tresDigitos($resto, $apocope) : $texto;
        }

        return self::tresDigitos($n, $apocope);
    }

    private static function tresDigitos(int $n, bool $apocope): string
    {
        if ($n === 100) {
            return 'cien';
        }

        $centenas = intdiv($n, 100);
        $resto = $n % 100;
        $partes = [];

        if ($centenas) {
            $partes[] = self::CENTENAS[$centenas];
        }

        if ($resto) {
            $partes[] = self::dosDigitos($resto, $apocope);
        }

        return implode(' ', $partes);
    }

    private static function dosDigitos(int $n, bool $apocope): string
    {
        if ($n < 30) {
            if ($apocope && $n === 1) {
                return 'un';
            }

            if ($apocope && $n === 21) {
                return 'veintiún';
            }

            return self::HASTA_29[$n];
        }

        $decena = self::DECENAS[intdiv($n, 10)];
        $unidad = $n % 10;

        if (! $unidad) {
            return $decena;
        }

        return $decena.' y '.($apocope && $unidad === 1 ? 'un' : self::HASTA_29[$unidad]);
    }
}
