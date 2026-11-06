<?php

namespace App\Console\Commands;

use App\Actions\Administracion\GenerarCuotasDelMes;
use App\Models\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Genera la cuota de administración del mes para cada vehículo en flota de las empresas que
 * tienen el módulo activo. Se corre el día 1 (programado) y es idempotente: si ya existe la
 * cuota del vehículo para ese periodo, no la duplica.
 *
 * El reparto por vehículo lo hace App\Actions\Administracion\GenerarCuotasDelMes (el mismo que
 * usa el botón del estado de cuenta).
 */
class GenerarCuotasAdministracion extends Command
{
    protected $signature = 'ruta:generar-cuotas-administracion {--mes= : Mes a generar (YYYY-MM); por defecto el mes en curso}';

    protected $description = 'Genera las cuotas de administración del mes por vehículo.';

    public function handle(GenerarCuotasDelMes $generar): int
    {
        $periodo = $this->option('mes')
            ? Carbon::createFromFormat('Y-m', $this->option('mes'))->startOfMonth()
            : now()->startOfMonth();

        $creadas = 0;
        $omitidos = 0;

        Empresa::query()->activas()->each(function (Empresa $empresa) use ($generar, $periodo, &$creadas, &$omitidos): void {
            $resultado = $generar($empresa, $periodo);
            $creadas += $resultado['creadas'];
            $omitidos += $resultado['omitidos'];
        });

        $this->info("Cuotas de administración de {$periodo->format('Y-m')}: {$creadas} generadas, {$omitidos} vehículos sin valor.");

        return self::SUCCESS;
    }
}
