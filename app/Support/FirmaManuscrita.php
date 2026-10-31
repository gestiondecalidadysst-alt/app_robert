<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Valida la imagen de la firma dibujada en la pantalla de firma (PNG en data URI).
 */
final class FirmaManuscrita
{
    public const MAX_BYTES = 600 * 1024;

    /** Devuelve el PNG binario o lanza un error de validación. */
    public static function desdeDataUri(?string $dataUri): string
    {
        $error = fn (string $m) => ValidationException::withMessages(['firma' => $m]);

        if (! is_string($dataUri) || ! str_starts_with($dataUri, 'data:image/png;base64,')) {
            throw $error('Dibuje su firma en el recuadro.');
        }

        $png = base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);

        if ($png === false || strlen($png) > self::MAX_BYTES) {
            throw $error('La imagen de la firma no es válida.');
        }

        $info = @getimagesizefromstring($png);
        if (! $info || $info[2] !== IMAGETYPE_PNG || $info[0] < 100 || $info[1] < 40) {
            throw $error('La imagen de la firma no es válida.');
        }

        if (self::trazos($png) < 60) {
            throw $error('La firma está vacía o es muy pequeña. Dibújela de nuevo.');
        }

        return $png;
    }

    /** Cantidad aproximada de puntos dibujados (no transparentes y oscuros), muestreando la imagen. */
    private static function trazos(string $png): int
    {
        $imagen = @imagecreatefromstring($png);
        if (! $imagen) {
            return 0;
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $paso = max(1, (int) floor(min($ancho, $alto) / 150));
        $puntos = 0;

        for ($x = 0; $x < $ancho; $x += $paso) {
            for ($y = 0; $y < $alto; $y += $paso) {
                $color = imagecolorsforindex($imagen, imagecolorat($imagen, $x, $y));
                $opaco = $color['alpha'] < 100;
                $oscuro = ($color['red'] + $color['green'] + $color['blue']) < 600;

                if ($opaco && $oscuro) {
                    $puntos++;
                }
            }
        }

        imagedestroy($imagen);

        return $puntos;
    }
}
