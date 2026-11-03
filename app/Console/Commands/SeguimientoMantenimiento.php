<?php

namespace App\Console\Commands;

use App\Actions\Mantenimiento\AvisosPrograma;
use App\Actions\Mantenimiento\SeguimientoPreventivo;
use App\Models\Empresa;
use App\Enums\EstadoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Console\Command;
use Throwable;

/**
 * Revisión diaria del mantenimiento preventivo:
 * - abre un plan de acción por cada rutina vencida (por fecha; las de kilometraje también se revisan
 *   al registrar preoperacionales y mantenimientos);
 * - avisa (webhook y campana) las actividades del programa anual próximas y vencidas.
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

        Empresa::query()->where('activa', true)->each(function (Empresa $empresa): void {
            try {
                $r = AvisosPrograma::enviar($empresa);
                if ($r['proximas'] || $r['vencidas']) {
                    $this->info("{$empresa->razon_social}: {$r['proximas']} próximas, {$r['vencidas']} vencidas.");
                }
            } catch (Throwable $e) {
                report($e);
            }
        });

        return self::SUCCESS;
    }
}
