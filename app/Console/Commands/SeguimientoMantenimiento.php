<?php

namespace App\Console\Commands;

use App\Actions\Mantenimiento\SeguimientoPreventivo;
use App\Enums\EstadoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Console\Command;
use Throwable;

/**
 * Revisión diaria del mantenimiento preventivo: abre un plan de acción por cada rutina vencida
 * (por fecha; las de kilometraje también se revisan al registrar preoperacionales y mantenimientos).
 */
class SeguimientoMantenimiento extends Command
{
    protected $signature = 'ruta:mantenimiento';

    protected $description = 'Abre planes de acción por rutinas preventivas vencidas';

    public function handle(): int
    {
        $abiertos = 0;

        Vehiculo::query()->withoutGlobalScopes()->whereNull('deleted_at')
            ->where('estado', '!=', EstadoVehiculo::Desvinculado->value)
            ->each(function (Vehiculo $vehiculo) use (&$abiertos): void {
                try {
                    $abiertos += SeguimientoPreventivo::revisar($vehiculo);
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("Planes de acción preventivos abiertos: {$abiertos}");

        return self::SUCCESS;
    }
}
