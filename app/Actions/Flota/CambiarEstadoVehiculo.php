<?php

namespace App\Actions\Flota;

use App\Enums\TipoNovedadVehiculo;
use App\Models\NovedadVehiculo;
use App\Models\Vehiculo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra una novedad del vehículo (suspensión, taller, desvinculación, reactivación o
 * vinculación) y cambia su estado. Es la única forma de cambiar el estado: así queda historial.
 */
class CambiarEstadoVehiculo
{
    public function __invoke(
        Vehiculo $vehiculo,
        TipoNovedadVehiculo $tipo,
        string $observacion,
        ?Carbon $fecha = null,
        ?Carbon $fechaReactivacion = null,
    ): NovedadVehiculo {
        if (! in_array($tipo, TipoNovedadVehiculo::disponiblesDesde($vehiculo->estado), true)) {
            throw ValidationException::withMessages([
                'tipo' => "No se puede registrar «{$tipo->getLabel()}» a un vehículo {$vehiculo->estado->getLabel()}.",
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

        return DB::transaction(function () use ($vehiculo, $tipo, $observacion, $fecha, $fechaReactivacion): NovedadVehiculo {
            $anterior = $vehiculo->estado;
            $nuevo = $tipo->estadoResultante();

            $novedad = NovedadVehiculo::create([
                'empresa_id' => $vehiculo->empresa_id,
                'vehiculo_id' => $vehiculo->id,
                'tipo' => $tipo,
                'fecha' => $fecha->toDateString(),
                'estado_anterior' => $anterior,
                'estado_nuevo' => $nuevo,
                'fecha_reactivacion' => $tipo->pideReactivacion() ? $fechaReactivacion?->toDateString() : null,
                'observacion' => trim($observacion),
                'user_id' => auth()->id(),
            ]);

            $vehiculo->forceFill(['estado' => $nuevo, 'estado_desde' => $fecha->toDateString()]);

            if ($tipo === TipoNovedadVehiculo::Vinculacion) {
                $vehiculo->fecha_vinculacion = $fecha->toDateString();
            }

            $vehiculo->save();

            return $novedad;
        });
    }
}
