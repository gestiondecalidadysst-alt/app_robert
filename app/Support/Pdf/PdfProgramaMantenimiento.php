<?php

namespace App\Support\Pdf;

use App\Actions\Mantenimiento\ProgramaAnual;
use App\Models\Empresa;
use App\Models\ProgramaMantenimiento;
use App\Models\Vehiculo;
use App\Support\Nit;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Cronograma anual de mantenimiento preventivo (PESV): por vehículo, rutinas × 12 meses con lo
 * programado (línea base) y lo ejecutado, reprogramado, cancelado o vencido; indicadores por
 * trimestre y firmas de quién elaboró y quién aprobó.
 */
final class PdfProgramaMantenimiento
{
    public static function generar(ProgramaMantenimiento $programa, ?Vehiculo $vehiculo = null): string
    {
        $empresa = Empresa::findOrFail($programa->empresa_id);
        $actividades = $programa->actividades()->with('vehiculo.claseVehiculo', 'vehiculo.marca', 'vehiculo.linea')
            ->when($vehiculo, fn ($q) => $q->where('vehiculo_id', $vehiculo->id))
            ->orderBy('vehiculo_id')->orderBy('fecha_original')->get();

        $html = view('pdf.programa-mantenimiento', [
            'programa' => $programa->load('elaboradoPor', 'aprobadoPor'),
            'porVehiculo' => $actividades->groupBy('vehiculo_id'),
            'indicadores' => ProgramaAnual::indicadores($programa, $vehiculo?->id),
            'vehiculo' => $vehiculo,
            'e' => [
                'razon_social' => $empresa->razon_social,
                'nit' => Nit::formatear($empresa->nit, $empresa->dv),
            ],
            'logo' => Imagenes::deDisco($empresa->logo_path, 'public'),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter', 'landscape')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])->output();
    }
}
