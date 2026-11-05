<?php

namespace App\Support\Pdf;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

/**
 * Imágenes para los PDF (dompdf no carga archivos remotos: todo va embebido como data URI).
 */
final class Imagenes
{
    /** QR PNG embebido. */
    public static function qr(string $url, int $escala = 4): string
    {
        $opciones = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'scale' => $escala,
            'outputBase64' => true,
            'quietzoneSize' => 1,
        ]);

        return (new QRCode($opciones))->render($url);
    }

    /** Archivo de un disco como data URI (null si no existe). */
    public static function deDisco(?string $ruta, string $disco): ?string
    {
        if (blank($ruta) || ! Storage::disk($disco)->exists($ruta)) {
            return null;
        }

        $mime = Storage::disk($disco)->mimeType($ruta) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk($disco)->get($ruta));
    }

    public static function deArchivo(string $ruta): string
    {
        return 'data:image/png;base64,'.base64_encode(file_get_contents($ruta));
    }
}
