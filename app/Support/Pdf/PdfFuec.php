<?php

namespace App\Support\Pdf;

use App\Models\Fuec;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

/**
 * Genera el PDF del FUEC a partir de la copia de datos guardada al expedirlo,
 * con un código QR que lleva a la verificación pública.
 */
final class PdfFuec
{
    public static function generar(Fuec $fuec): string
    {
        $datos = $fuec->datos;
        $logo = $datos['empresa']['logo_path'] ?? null;

        $html = view('pdf.fuec', [
            'fuec' => $fuec,
            'd' => $datos,
            'qr' => self::qr($fuec->urlVerificacion()),
            'logo' => $logo && Storage::disk('public')->exists($logo)
                ? 'data:' . Storage::disk('public')->mimeType($logo) . ';base64,' . base64_encode(Storage::disk('public')->get($logo))
                : null,
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('letter')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])
            ->output();
    }

    public static function nombreArchivo(Fuec $fuec): string
    {
        return "FUEC-{$fuec->numero}.pdf";
    }

    /** QR como imagen PNG embebida (data URI). */
    private static function qr(string $url): string
    {
        $opciones = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'scale' => 4,
            'outputBase64' => true,
            'quietzoneSize' => 1,
        ]);

        return (new QRCode($opciones))->render($url);
    }
}
