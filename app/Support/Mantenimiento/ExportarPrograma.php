<?php

namespace App\Support\Mantenimiento;

use App\Actions\Mantenimiento\ProgramaAnual;
use App\Models\ActividadMantenimiento;
use App\Models\Mantenimiento;
use App\Models\ProgramaMantenimiento;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Excel para el reporte de la Supertransporte (PESV, vehículos):
 * 1. Indicador: actividades de mantenimiento preventivo programadas y ejecutadas por trimestre.
 * 2. Actividades del programa con su estado y trazabilidad.
 * 3. Relación de mantenimientos ejecutados (tipo preventivo/correctivo y establecimiento).
 */
final class ExportarPrograma
{
    public static function excel(ProgramaMantenimiento $programa): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'pesv').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($ruta);

        $writer->getCurrentSheet()->setName('Indicador trimestral');
        $writer->addRow(Row::fromValues(["Programa de mantenimiento {$programa->anio} · versión {$programa->version} · {$programa->estado->getLabel()}"]));
        $writer->addRow(Row::fromValues(['Trimestre', 'Actividades de mantenimiento preventivo programadas', 'Ejecutadas', 'Canceladas', 'Programadas ya vencidas (exigibles)', '% cumplimiento a la fecha']));
        foreach (ProgramaAnual::indicadores($programa) as $t => $i) {
            $writer->addRow(Row::fromValues([$t, $i['programadas'], $i['ejecutadas'], $i['canceladas'], $i['exigibles'], $i['cumplimiento']]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Actividades');
        $writer->addRow(Row::fromValues(['Placa', 'No. interno', 'Rutina', 'Frecuencia', 'Fecha programada (línea base)', 'Km programado', 'Fecha programada actual',
            'Estado', 'Fecha ejecución', 'Km ejecución', 'Días de desviación', 'Incorporada', 'Motivo', 'Historial']));
        $programa->actividades()->with('vehiculo')->orderBy('fecha_original')->get()->each(function (ActividadMantenimiento $a) use ($writer): void {
            $writer->addRow(Row::fromValues([
                $a->vehiculo?->placa, $a->vehiculo?->numero_interno, $a->rutina_nombre, $a->frecuencia,
                $a->fecha_original->format('Y-m-d'), $a->km_original, $a->fecha_programada->format('Y-m-d'),
                $a->situacion(0), $a->fecha_ejecucion?->format('Y-m-d'), $a->km_ejecucion, $a->desviacionDias(),
                $a->incorporada ? 'Sí' : 'No', $a->motivo,
                collect($a->historial ?? [])->map(fn ($h) => "{$h['fecha']} {$h['tipo']}: {$h['detalle']}")->implode(' | '),
            ]));
        });

        $writer->addNewSheetAndMakeItCurrent()->setName('Mantenimientos ejecutados');
        $writer->addRow(Row::fromValues(['Placa', 'Fecha', 'Tipo de mantenimiento', 'Acción', 'Establecimiento o centro especializado',
            'Responsable', 'Kilometraje', 'Actividad', 'Rutinas', 'Valor', 'Factura', 'Evidencias']));
        Mantenimiento::query()->deEmpresa($programa->empresa_id)->reales()->whereYear('fecha', $programa->anio)
            ->with(['vehiculo', 'rutinas'])->withCount('evidencias')->orderBy('fecha')->get()
            ->each(fn (Mantenimiento $m) => $writer->addRow(Row::fromValues([
                $m->vehiculo?->placa, $m->fecha->format('Y-m-d'), $m->tipo->getLabel(), $m->accion->getLabel(), $m->taller,
                $m->responsable_nombre, $m->kilometraje, $m->actividad, $m->rutinas->pluck('nombre')->implode('; '),
                $m->valor !== null ? (float) $m->valor : null, $m->factura_numero, $m->evidencias_count,
            ])));

        $writer->close();

        return $ruta;
    }
}
