<?php

namespace App\Actions\Conductores;

use App\Enums\TipoNovedadConductor;
use App\Models\Conductor;
use App\Models\NovedadConductor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra una novedad del conductor (suspensión, retiro, reactivación o reintegro) y cambia
 * su estado. El retiro termina sus asignaciones de vehículo vigentes.
 */
class CambiarEstadoConductor
{
    public function __invoke(
        Conductor $conductor,
        TipoNovedadConductor $tipo,
        string $observacion,
        ?Carbon $fecha = null,
        ?Carbon $fechaReactivacion = null,
    ): NovedadConductor {
        if (! in_array($tipo, TipoNovedadConductor::disponiblesDesde($conductor->estado), true)) {
            throw ValidationException::withMessages([
                'tipo' => "No se puede registrar «{$tipo->getLabel()}» a un conductor " . mb_strtolower($conductor->estado->getLabel()) . '.',
            ]);
        }

        if (blank(trim($observacion))) {
            throw ValidationException::withMessages(['observacion' => 'La observación es obligatoria.']);
        }

        $fecha ??= now();

        if ($fechaReactivacion && $fechaReactivacion->lt($fecha->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'fecha_reactivacion' => 'La fecha prevista de reactivación no puede ser anterior a la novedad.',
            ]);
        }

        return DB::transaction(function () use ($conductor, $tipo, $observacion, $fecha, $fechaReactivacion): NovedadConductor {
            $novedad = NovedadConductor::create([
                'empresa_id' => $conductor->empresa_id,
                'conductor_id' => $conductor->id,
                'tipo' => $tipo,
                'fecha' => $fecha->toDateString(),
                'estado_anterior' => $conductor->estado,
                'estado_nuevo' => $tipo->estadoResultante(),
                'fecha_reactivacion' => $tipo->pideReactivacion() ? $fechaReactivacion?->toDateString() : null,
                'observacion' => trim($observacion),
                'user_id' => auth()->id(),
            ]);

            $conductor->forceFill(['estado' => $tipo->estadoResultante(), 'estado_desde' => $fecha->toDateString()])->save();

            if ($tipo === TipoNovedadConductor::Retiro) {
                $conductor->asignacionesVigentes()->update(['fecha_hasta' => $fecha->toDateString()]);
            }

            return $novedad;
        });
    }
}
