<?php

namespace App\Support;

/**
 * Utilidades de SMS. El cobro de Onurix es por segmento, no por mensaje: un texto de más de
 * 160 caracteres se parte en varios. Los SMS del sistema van sin tildes (GSM-7), así que se
 * usa la regla GSM: 160 por un solo segmento, 153 por segmento cuando son varios.
 */
final class Sms
{
    public static function segmentos(string $texto): int
    {
        $longitud = mb_strlen($texto);

        if ($longitud === 0) {
            return 0;
        }

        return $longitud <= 160 ? 1 : (int) ceil($longitud / 153);
    }
}
