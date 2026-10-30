<?php

namespace App\Actions\Conductores;

use App\Enums\EstadoConductor;
use App\Enums\EstadoVehiculo;
use App\Models\AsignacionVehiculo;
use App\Models\Conductor;
use App\Models\Vehiculo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Asigna un conductor a un vehículo, o termina una asignación.
 * - No repite una asignación vigente del mismo conductor y vehículo.
 * - No asigna conductores retirados ni vehículos desvinculados.
 * - Un vehículo tiene a lo sumo un conductor principal vigente: marcar otro quita la marca al anterior.
 */
class AsignarVehiculo
{
    public function __invoke(
        Conductor $conductor,
        Vehiculo $vehiculo,
        ?Carbon $desde = null,
        bool $principal = false,
        ?string $observacion = null,
    ): AsignacionVehiculo {
        if ($conductor->empresa_id !== $vehiculo->empresa_id) {
            throw ValidationException::withMessages(['vehiculo_id' => 'El vehículo es de otra empresa.']);
        }

        if ($conductor->estado === EstadoConductor::Retirado) {
            throw ValidationException::withMessages(['conductor_id' => 'El conductor está retirado.']);
        }

        if ($vehiculo->estado === EstadoVehiculo::Desvinculado) {
            throw ValidationException::withMessages(['vehiculo_id' => "El vehículo {$vehiculo->placa} está desvinculado."]);
        }

        $repetida = AsignacionVehiculo::query()->deEmpresa($conductor->empresa_id)
            ->where('conductor_id', $conductor->id)->where('vehiculo_id', $vehiculo->id)->vigentes()->exists();

        if ($repetida) {
            throw ValidationException::withMessages(['vehiculo_id' => "{$conductor->nombre_completo} ya está asignado a {$vehiculo->placa}."]);
        }

        return DB::transaction(function () use ($conductor, $vehiculo, $desde, $principal, $observacion): AsignacionVehiculo {
            if ($principal) {
                AsignacionVehiculo::query()->deEmpresa($vehiculo->empresa_id)
                    ->where('vehiculo_id', $vehiculo->id)->vigentes()->update(['principal' => false]);
            }

            return AsignacionVehiculo::create([
                'empresa_id' => $conductor->empresa_id,
                'conductor_id' => $conductor->id,
                'vehiculo_id' => $vehiculo->id,
                'fecha_desde' => ($desde ?? now())->toDateString(),
                'principal' => $principal,
                'observacion' => $observacion,
            ]);
        });
    }

    public function terminar(AsignacionVehiculo $asignacion, ?Carbon $hasta = null): void
    {
        $hasta ??= now();

        if ($hasta->lt($asignacion->fecha_desde)) {
            throw ValidationException::withMessages(['fecha_hasta' => 'La fecha de fin no puede ser anterior al inicio.']);
        }

        $asignacion->update(['fecha_hasta' => $hasta->toDateString(), 'principal' => false]);
    }
}
