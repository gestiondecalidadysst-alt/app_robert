<?php

namespace App\Support\Pdf;

use App\Models\Vehiculo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Informe de mantenimientos por vehículo (o toda la flota) y rango de fechas. */
final class PdfMantenimientos
{
    public static function informe(Collection $registros, Carbon $desde, Carbon $hasta, ?Vehiculo $vehiculo, array $empresa): string
    {
        $html = view('pdf.informe-mantenimientos', [
            'registros' => $registros,
            'desde' => $desde,
            'hasta' => $hasta,
            'vehiculo' => $vehiculo,
            'e' => $empresa,
            'logo' => Imagenes::deDisco($empresa['logo_path'] ?? null, 'public'),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter', 'landscape')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])->output();
    }
}
