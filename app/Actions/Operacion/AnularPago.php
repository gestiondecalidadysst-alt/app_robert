<?php

namespace App\Actions\Operacion;

use App\Models\PagoContrato;
use Illuminate\Validation\ValidationException;

/**
 * Anula un pago: deja de contar en el saldo del contrato y su recibo conserva el número, marcado
 * ANULADO (no se borra para no romper el consecutivo de recibos).
 */
class AnularPago
{
    public function __invoke(PagoContrato $pago, string $motivo): PagoContrato
    {
        if ($pago->anulado()) {
            throw ValidationException::withMessages(['motivo' => 'El pago ya está anulado.']);
        }
        if (mb_strlen(trim($motivo)) < 5) {
            throw ValidationException::withMessages(['motivo' => 'Escriba el motivo de la anulación.']);
        }

        $pago->forceFill([
            'anulado_at' => now(),
            'anulado_por' => auth()->id(),
            'motivo_anulacion' => trim($motivo),
        ])->save();

        return $pago;
    }
}
