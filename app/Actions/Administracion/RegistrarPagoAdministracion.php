<?php

namespace App\Actions\Administracion;

use App\Models\CuotaAdministracion;
use App\Models\Empresa;
use App\Models\PagoAdministracion;
use App\Models\Vehiculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra el pago de la administración de un vehículo y lo reparte entre las cuotas pendientes,
 * de la más vieja a la más nueva (FIFO). Le asigna un recibo con consecutivo propio por empresa.
 * No admite pagos mayores que lo que el vehículo debe hoy.
 */
class RegistrarPagoAdministracion
{
    public function __invoke(Vehiculo $vehiculo, array $datos): PagoAdministracion
    {
        $valor = (int) round((float) ($datos['valor'] ?? 0));
        if ($valor <= 0) {
            throw ValidationException::withMessages(['valor' => 'El valor debe ser mayor que cero.']);
        }

        return DB::transaction(function () use ($vehiculo, $datos, $valor) {
            $empresa = Empresa::query()->whereKey($vehiculo->empresa_id)->lockForUpdate()->firstOrFail();

            // Cuotas con saldo, de la más vieja a la más nueva, bloqueadas para el reparto.
            $cuotas = CuotaAdministracion::query()
                ->withoutGlobalScopes()
                ->where('empresa_id', $empresa->id)
                ->where('vehiculo_id', $vehiculo->id)
                ->whereNull('anulado_at')
                ->whereColumn('pagado', '<', 'valor')
                ->orderBy('periodo')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $pendiente = $cuotas->sum(fn (CuotaAdministracion $c) => $c->saldo());
            if ($pendiente <= 0) {
                throw ValidationException::withMessages(['valor' => 'Este vehículo no tiene cuotas pendientes.']);
            }
            if ($valor > $pendiente) {
                throw ValidationException::withMessages([
                    'valor' => 'El pago supera lo pendiente ($ '.number_format($pendiente, 0, ',', '.').').',
                ]);
            }

            $ultimo = (int) PagoAdministracion::query()->withoutGlobalScopes()->where('empresa_id', $empresa->id)->max('numero');

            $pago = new PagoAdministracion([
                ...collect($datos)->only(['fecha', 'metodo', 'referencia', 'observacion', 'user_id'])->all(),
                'empresa_id' => $empresa->id,
                'vehiculo_id' => $vehiculo->id,
                'valor' => $valor,
            ]);
            $pago->forceFill([
                'numero' => max($ultimo + 1, (int) ($empresa->recibo_numero_inicial ?: 1)),
            ]);
            $pago->save();

            // Reparto FIFO: abona a cada cuota lo que le falte hasta agotar el pago.
            $resto = $valor;
            foreach ($cuotas as $cuota) {
                if ($resto <= 0) {
                    break;
                }
                $abono = min($resto, $cuota->saldo());
                if ($abono <= 0) {
                    continue;
                }
                $pago->cuotas()->attach($cuota->id, ['valor' => $abono]);
                $cuota->forceFill(['pagado' => (int) $cuota->pagado + $abono])->save();
                $resto -= $abono;
            }

            return $pago;
        });
    }
}
