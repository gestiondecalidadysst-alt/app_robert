<?php

namespace App\Actions\Administracion;

use App\Enums\ModuloEmpresa;
use App\Models\CuotaAdministracion;
use App\Models\Empresa;
use App\Models\ValorAdministracion;
use App\Models\Vehiculo;
use App\Support\Modulos;
use Illuminate\Support\Carbon;

/**
 * Genera las cuotas de administración de un mes para una empresa: una por cada vehículo en flota
 * que tenga valor (ajuste propio o el vigente de su clase). Es idempotente: si ya existe la cuota
 * del vehículo para ese periodo, no la duplica. Si la empresa no tiene el módulo, no hace nada.
 *
 * Lo usan el comando programado (ruta:generar-cuotas-administracion) y el botón del estado de cuenta.
 *
 * @return array{creadas: int, omitidos: int}
 */
class GenerarCuotasDelMes
{
    public function __invoke(Empresa $empresa, ?Carbon $periodo = null): array
    {
        $periodo = ($periodo ?? now())->copy()->startOfMonth();

        if (! Modulos::activo($empresa, ModuloEmpresa::Administracion)) {
            return ['creadas' => 0, 'omitidos' => 0];
        }

        $creadas = 0;
        $omitidos = 0;

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

        return ['creadas' => $creadas, 'omitidos' => $omitidos];
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
