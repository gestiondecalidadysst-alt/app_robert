<?php

namespace App\Support\Pdf;

use App\Models\SeccionManual;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Manual de la aplicación en PDF (tamaño carta), para capacitar o entregar impreso.
 */
final class PdfManual
{
    public static function completo(): string
    {
        $grupos = SeccionManual::query()->ordenado()->get()->groupBy('grupo');

        $html = view('pdf.manual', ['grupos' => $grupos])->render();

        return Pdf::loadHTML($html)->setPaper('letter')
            ->setOption(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false])
            ->output();
    }

    public static function nombre(): string
    {
        return 'manual-plataforma-'.now()->format('Y-m-d').'.pdf';
    }
}
