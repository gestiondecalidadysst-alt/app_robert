<?php

namespace App\Actions\Operacion;

use App\Models\Contrato;
use App\Models\PagoContrato;
use Illuminate\Validation\ValidationException;

/**
 * Registra un abono a un contrato. No admite pagos a contratos anulados ni mayores que el saldo.
 */
class RegistrarPago
{
    public function __invoke(Contrato $contrato, array $datos): PagoContrato
    {
        if ($contrato->anulado()) {
            throw ValidationException::withMessages(['valor' => 'El contrato está anulado.']);
        }

        $valor = round((float) ($datos['valor'] ?? 0), 2);
        if ($valor <= 0) {
            throw ValidationException::withMessages(['valor' => 'El valor debe ser mayor que cero.']);
        }

        $saldo = $contrato->saldo();
        if ($valor > $saldo) {
            throw ValidationException::withMessages([
                'valor' => 'El pago supera el saldo del contrato ($ ' . number_format($saldo, 0, ',', '.') . ').',
            ]);
        }

        return $contrato->pagos()->create([...$datos, 'empresa_id' => $contrato->empresa_id, 'valor' => $valor]);
    }
}
