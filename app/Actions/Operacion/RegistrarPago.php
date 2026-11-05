<?php

namespace App\Actions\Operacion;

use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\PagoContrato;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registra un pago o abono a un contrato y le asigna su recibo de caja:
 * - Consecutivo por empresa (prefijo y número inicial configurables en Integraciones → Recibos).
 * - Guarda el saldo antes y después del pago (lo que imprime el recibo).
 * No admite pagos a contratos anulados ni mayores que el saldo.
 */
class RegistrarPago
{
    public function __invoke(Contrato $contrato, array $datos): PagoContrato
    {
        $valor = round((float) ($datos['valor'] ?? 0), 2);
        if ($valor <= 0) {
            throw ValidationException::withMessages(['valor' => 'El valor debe ser mayor que cero.']);
        }

        return DB::transaction(function () use ($contrato, $datos, $valor) {
            // Bloqueos: el contrato (saldo) y la empresa (consecutivo de recibos).
            $contrato = Contrato::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($contrato->getKey());
            $empresa = Empresa::query()->whereKey($contrato->empresa_id)->lockForUpdate()->firstOrFail();

            if ($contrato->anulado()) {
                throw ValidationException::withMessages(['valor' => 'El contrato está anulado.']);
            }

            $saldo = $contrato->saldo();
            if ($valor > $saldo) {
                throw ValidationException::withMessages([
                    'valor' => 'El pago supera el saldo del contrato ($ '.number_format($saldo, 0, ',', '.').').',
                ]);
            }

            $ultimo = (int) PagoContrato::query()->withoutGlobalScopes()->where('empresa_id', $empresa->id)->max('numero');

            $pago = new PagoContrato([
                ...collect($datos)->only(['fecha', 'metodo', 'referencia', 'observacion', 'user_id'])->all(),
                'empresa_id' => $contrato->empresa_id,
                'contrato_id' => $contrato->id,
                'valor' => $valor,
            ]);
            $pago->forceFill([
                'prefijo' => trim((string) $empresa->recibo_prefijo) ?: null,
                'numero' => max($ultimo + 1, (int) ($empresa->recibo_numero_inicial ?: 1)),
                'codigo_verificacion' => Str::lower(Str::random(24)),
                'saldo_anterior' => $saldo,
                'saldo_despues' => round($saldo - $valor, 2),
            ]);
            $pago->setRelation('contrato', $contrato);
            $pago->save();

            return $pago;
        });
    }
}
