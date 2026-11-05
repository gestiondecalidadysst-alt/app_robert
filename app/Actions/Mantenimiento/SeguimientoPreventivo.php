<?php

namespace App\Actions\Mantenimiento;

use App\Enums\OrigenPlan;
use App\Enums\PrioridadPlan;
use App\Models\PlanAccion;
use App\Models\Vehiculo;
use App\Support\Mantenimiento\PlanPreventivo;
use App\Support\Webhooks;

/**
 * Abre un plan de acción (prioridad media: solo alerta, no bloquea) por cada rutina preventiva
 * vencida que aún no tenga uno pendiente. Corre al registrar un preoperacional o un mantenimiento
 * (cambia el kilometraje) y cada día con `ruta:mantenimiento` (vencimientos por fecha).
 */
final class SeguimientoPreventivo
{
    /** @return int planes abiertos */
    public static function revisar(Vehiculo $vehiculo): int
    {
        $plan = PlanPreventivo::para($vehiculo)->where('estado', PlanPreventivo::VENCIDO);
        if ($plan->isEmpty()) {
            return 0;
        }

        $pendientes = PlanAccion::query()->deEmpresa($vehiculo->empresa_id)->where('vehiculo_id', $vehiculo->id)->pendientes()
            ->where('origen', OrigenPlan::Preventivo->value)->with('rutina')->get()
            ->map(fn (PlanAccion $p) => PlanPreventivo::clave($p->rutina?->nombre ?? $p->titulo))->all();

        $abiertos = 0;
        foreach ($plan as $fila) {
            $rutina = $fila['rutina'];
            if (in_array(PlanPreventivo::clave($rutina->nombre), $pendientes, true)) {
                continue;
            }

            $nuevo = new PlanAccion([
                'vehiculo_id' => $vehiculo->id,
                'origen' => OrigenPlan::Preventivo,
                'rutina_mantenimiento_id' => $rutina->id,
                'prioridad' => PrioridadPlan::Media,
                'titulo' => "Mantenimiento preventivo vencido: {$rutina->nombre}",
                'hallazgos' => [[
                    'descripcion' => $rutina->nombre,
                    'critico' => false,
                    'observacion' => PlanPreventivo::textoFaltante($fila).'. Último: '.$fila['ultimo']->fecha->format('d/m/Y')
                        .($fila['ultimo']->kilometraje !== null ? ' a '.number_format($fila['ultimo']->kilometraje, 0, ',', '.').' km' : '').'.',
                    'fecha' => today()->toDateString(),
                    'veces' => 1,
                ]],
                'accion' => "Realizar «{$rutina->nombre}» ({$rutina->frecuencia}) y registrar el mantenimiento con su evidencia.",
                'responsable' => $vehiculo->propietario?->nombre_completo ?? 'Propietario del vehículo',
                'fecha_limite' => today()->addDays(PlanPreventivo::AVISO_DIAS)->toDateString(),
            ]);
            $nuevo->empresa_id = $vehiculo->empresa_id;
            $nuevo->save();
            $abiertos++;
            Webhooks::emitir($vehiculo->empresa_id, 'plan_accion.creado', RegistrarMantenimiento::datosPlan($nuevo, $vehiculo));
        }

        return $abiertos;
    }
}
