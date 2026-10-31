<?php

namespace App\Support\Pdf;

use App\Models\Fuec;
use Barryvdh\DomPDF\Facade\Pdf;

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
            'qr' => Imagenes::qr($fuec->urlVerificacion()),
            'vigilado' => Imagenes::deArchivo(resource_path('images/vigilado-supertransporte.png')),
            'logo' => Imagenes::deDisco($logo, 'public'),
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
}
