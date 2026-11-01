<?php

namespace App\Actions\Mantenimiento;

use App\Enums\EstadoPlan;
use App\Enums\OrigenPlan;
use App\Enums\PrioridadPlan;
use App\Models\Empresa;
use App\Models\PlanAccion;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Cambios de estado de un plan de acción:
 * abierto → en ejecución → por verificar (con evidencia) → cerrado (lo aprueba la empresa)
 *                                                      ↘ rechazado (vuelve a ejecución con el motivo)
 */
final class GestionarPlanAccion
{
    public static function crear(Empresa $empresa, Vehiculo $vehiculo, array $datos, ?User $usuario = null): PlanAccion
    {
        $prioridad = ($datos['prioridad'] ?? null) instanceof PrioridadPlan ? $datos['prioridad'] : PrioridadPlan::tryFrom((string) ($datos['prioridad'] ?? ''));
        if (blank($datos['titulo'] ?? null) || ! $prioridad) {
            throw ValidationException::withMessages(['titulo' => 'Indique el hallazgo y la prioridad.']);
        }

        $plan = new PlanAccion([
            'vehiculo_id' => $vehiculo->id,
            'origen' => OrigenPlan::Manual,
            'prioridad' => $prioridad,
            'titulo' => trim($datos['titulo']),
            'hallazgos' => [['descripcion' => trim($datos['titulo']), 'critico' => $prioridad === PrioridadPlan::Alta, 'observacion' => $datos['detalle'] ?? null, 'fecha' => today()->toDateString(), 'veces' => 1]],
            'accion' => $datos['accion'] ?? null,
            'responsable' => filled($datos['responsable'] ?? null) ? $datos['responsable'] : ($vehiculo->propietario?->nombre_completo ?? 'Propietario del vehículo'),
            'fecha_limite' => filled($datos['fecha_limite'] ?? null) ? Carbon::parse($datos['fecha_limite'])->toDateString() : null,
            'user_id' => $usuario?->id,
        ]);
        $plan->empresa_id = $empresa->id;
        $plan->save();

        return $plan;
    }

    public static function iniciar(PlanAccion $plan): void
    {
        self::exigirPendiente($plan);
        $plan->forceFill(['estado' => EstadoPlan::EnEjecucion])->save();
    }

    /** @param  list<UploadedFile>  $archivos */
    public static function adjuntarEvidencia(PlanAccion $plan, array $archivos, ?string $nota = null, ?User $usuario = null): void
    {
        self::exigirPendiente($plan);
        if ($archivos === []) {
            throw ValidationException::withMessages(['archivos' => 'Adjunte al menos un archivo.']);
        }

        GuardarEvidencias::en($plan, $archivos, $usuario, filled($nota) ? trim($nota) : null);
        $plan->forceFill(['estado' => EstadoPlan::PorVerificar])->save();
    }

    /** La empresa revisa la evidencia y cierra. */
    public static function cerrar(PlanAccion $plan, ?string $observacion, ?User $usuario = null): void
    {
        self::exigirPendiente($plan);
        if ($plan->evidencias()->doesntExist() && $plan->mantenimientos()->doesntExist() && blank($observacion)) {
            throw ValidationException::withMessages(['observacion' => 'El plan no tiene evidencias ni mantenimientos: explique en la observación por qué se cierra.']);
        }

        $plan->forceFill([
            'estado' => EstadoPlan::Cerrado,
            'cerrado_at' => now(),
            'cerrado_por' => $usuario?->id,
            'observacion_cierre' => filled($observacion) ? trim($observacion) : null,
        ])->save();
    }

    /** La evidencia no sirve: vuelve a ejecución con el motivo. */
    public static function rechazar(PlanAccion $plan, string $motivo): void
    {
        self::exigirPendiente($plan);
        if (blank($motivo)) {
            throw ValidationException::withMessages(['motivo' => 'Indique por qué se rechaza.']);
        }

        $plan->forceFill(['estado' => EstadoPlan::Rechazado, 'observacion_cierre' => trim($motivo)])->save();
    }

    private static function exigirPendiente(PlanAccion $plan): void
    {
        if (! $plan->pendiente()) {
            throw ValidationException::withMessages(['estado' => 'El plan ya está cerrado.']);
        }
    }
}
