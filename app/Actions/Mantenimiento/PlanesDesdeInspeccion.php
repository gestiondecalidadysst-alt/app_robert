<?php

namespace App\Actions\Mantenimiento;

use App\Enums\EstadoPlan;
use App\Enums\OrigenPlan;
use App\Enums\PrioridadPlan;
use App\Enums\TipoInspeccion;
use App\Models\Inspeccion;
use App\Models\PlanAccion;
use App\Models\Vehiculo;
use App\Support\Webhooks;

/**
 * Las fallas de un preoperacional o una inspección mensual abren (o alimentan) el plan de acción del
 * vehículo. Un solo plan pendiente por vehículo para las fallas de inspección:
 * - Falla nueva: se agrega a los hallazgos.
 * - Falla repetida: suma una reincidencia (no abre otro plan).
 * - Falla crítica: prioridad ALTA (el vehículo no queda apto hasta cerrar el plan); si no, MEDIA.
 */
final class PlanesDesdeInspeccion
{
    public static function procesar(Inspeccion $inspeccion): ?PlanAccion
    {
        $fallas = $inspeccion->fallas();
        if ($fallas === []) {
            return null;
        }

        $vehiculo = Vehiculo::withTrashed()->find($inspeccion->vehiculo_id);
        $critica = collect($fallas)->contains('critico', true);
        $fecha = $inspeccion->fecha->toDateString();
        $nuevos = collect($fallas)->map(fn (array $f) => [
            'descripcion' => $f['descripcion'],
            'grupo' => $f['grupo'] ?? null,
            'critico' => (bool) $f['critico'],
            'observacion' => $f['observacion'] ?? null,
            'fecha' => $fecha,
            'inspeccion_id' => $inspeccion->id,
            'veces' => 1,
        ]);

        $plan = PlanAccion::query()->deEmpresa($inspeccion->empresa_id)->where('vehiculo_id', $inspeccion->vehiculo_id)->pendientes()
            ->whereIn('origen', [OrigenPlan::Preoperacional->value, OrigenPlan::Inspeccion->value])
            ->oldest('id')->first();

        if ($plan) {
            $hallazgos = collect($plan->hallazgos ?? []);
            $repetidas = 0;

            foreach ($nuevos as $nuevo) {
                $i = $hallazgos->search(fn (array $h) => mb_strtolower($h['descripcion']) === mb_strtolower($nuevo['descripcion']));
                if ($i === false) {
                    $hallazgos->push($nuevo);
                } else {
                    $h = $hallazgos[$i];
                    $hallazgos[$i] = [...$h, 'veces' => ($h['veces'] ?? 1) + 1, 'fecha' => $fecha, 'observacion' => $nuevo['observacion'] ?: ($h['observacion'] ?? null), 'inspeccion_id' => $inspeccion->id];
                    $repetidas++;
                }
            }

            $plan->hallazgos = $hallazgos->values()->all();
            $plan->reincidencias += $repetidas;
            if ($critica) {
                $plan->prioridad = PrioridadPlan::Alta;
            }
            // Si ya estaba por verificar o rechazado y vuelve a fallar, sigue en ejecución.
            if (in_array($plan->estado, [EstadoPlan::PorVerificar, EstadoPlan::Rechazado], true)) {
                $plan->estado = EstadoPlan::EnEjecucion;
            }
            $plan->save();

            return $plan;
        }

        $plan = new PlanAccion([
            'vehiculo_id' => $inspeccion->vehiculo_id,
            'origen' => $inspeccion->tipo === TipoInspeccion::Mensual ? OrigenPlan::Inspeccion : OrigenPlan::Preoperacional,
            'inspeccion_id' => $inspeccion->id,
            'prioridad' => $critica ? PrioridadPlan::Alta : PrioridadPlan::Media,
            'titulo' => ($inspeccion->tipo === TipoInspeccion::Mensual ? 'Fallas en la inspección mensual' : 'Fallas en el preoperacional')
                ." del {$inspeccion->fecha->format('d/m/Y')}",
            'hallazgos' => $nuevos->values()->all(),
            'accion' => 'Corregir: '.$nuevos->pluck('descripcion')->implode('; ').'. Registrar el mantenimiento con la evidencia (factura o fotos).',
            'responsable' => $vehiculo?->propietario?->nombre_completo ?? 'Propietario del vehículo',
            'fecha_limite' => today()->addDays($critica ? 1 : 7)->toDateString(),
            'user_id' => $inspeccion->user_id,
        ]);
        $plan->empresa_id = $inspeccion->empresa_id;
        $plan->save();

        Webhooks::emitir($plan->empresa_id, 'plan_accion.creado', RegistrarMantenimiento::datosPlan($plan, $vehiculo));

        return $plan;
    }
}
