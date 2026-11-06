<?php

namespace App\Console\Commands;

use App\Enums\ModuloEmpresa;
use App\Models\CuotaAdministracion;
use App\Models\Empresa;
use App\Models\ValorAdministracion;
use App\Models\Vehiculo;
use App\Support\Modulos;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Genera la cuota de administración del mes para cada vehículo en flota de las empresas que
 * tienen el módulo activo. Se corre el día 1 (programado) y es idempotente: si ya existe la
 * cuota del vehículo para ese periodo, no la duplica (índice único empresa+vehículo+periodo).
 *
 * El valor sale del ajuste del vehículo (administracion_valor) o, si no, del valor vigente de su
 * clase (ValorAdministracion). Si no hay ninguno, el vehículo se omite y se reporta.
 */
class GenerarCuotasAdministracion extends Command
{
    protected $signature = 'ruta:generar-cuotas-administracion {--mes= : Mes a generar (YYYY-MM); por defecto el mes en curso}';

    protected $description = 'Genera las cuotas de administración del mes por vehículo.';

    public function handle(): int
    {
        $periodo = $this->option('mes')
            ? Carbon::createFromFormat('Y-m', $this->option('mes'))->startOfMonth()
            : now()->startOfMonth();

        $creadas = 0;
        $omitidos = 0;

        Empresa::query()->activas()->each(function (Empresa $empresa) use ($periodo, &$creadas, &$omitidos): void {
            if (! Modulos::activo($empresa, ModuloEmpresa::Administracion)) {
                return;
            }

            Vehiculo::query()->deEmpresa($empresa)->enFlota()->each(function (Vehiculo $vehiculo) use ($empresa, $periodo, &$creadas, &$omitidos): void {
                $valor = $this->valorCuota($empresa, $vehiculo, $periodo);
                if ($valor === null || $valor <= 0) {
                    $omitidos++;

                    return;
                }

                $existe = CuotaAdministracion::query()->deEmpresa($empresa)
                    ->where('vehiculo_id', $vehiculo->id)
                    ->whereDate('periodo', $periodo->toDateString())
                    ->exists();
                if ($existe) {
                    return;
                }

                $cuota = new CuotaAdministracion([
                    'vehiculo_id' => $vehiculo->id,
                    'propietario_id' => $vehiculo->propietario_id,
                    'clase_vehiculo_id' => $vehiculo->clase_vehiculo_id,
                    'periodo' => $periodo,
                    'valor' => $valor,
                    'pagado' => 0,
                ]);
                $cuota->empresa_id = $empresa->id;
                $cuota->save();
                $creadas++;
            });
        });

        $this->info("Cuotas de administración de {$periodo->format('Y-m')}: {$creadas} generadas, {$omitidos} vehículos sin valor.");

        return self::SUCCESS;
    }

    /** Valor de la cuota del vehículo: su ajuste propio o el vigente de su clase. */
    private function valorCuota(Empresa $empresa, Vehiculo $vehiculo, Carbon $periodo): ?int
    {
        if ($vehiculo->administracion_valor !== null) {
            return (int) $vehiculo->administracion_valor;
        }

        if (! $vehiculo->clase_vehiculo_id) {
            return null;
        }

        $valor = ValorAdministracion::vigente($empresa, (int) $vehiculo->clase_vehiculo_id, $periodo);

        return $valor ? (int) round((float) $valor->valor) : null;
    }
}
