<?php

namespace App\Actions\Administracion;

use App\Models\CuotaAdministracion;
use App\Models\PagoAdministracion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anula un recibo de administración y devuelve a cada cuota lo que este pago le había abonado,
 * para que el estado de cuenta vuelva a mostrarlas pendientes. No se borra: queda el rastro.
 */
class AnularPagoAdministracion
{
    public function __invoke(PagoAdministracion $pago, string $motivo, ?int $userId = null): PagoAdministracion
    {
        if ($pago->anulado()) {
            throw ValidationException::withMessages(['motivo' => 'El recibo ya está anulado.']);
        }

        return DB::transaction(function () use ($pago, $motivo, $userId) {
            $pago = PagoAdministracion::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($pago->getKey());

            foreach ($pago->cuotas()->withoutGlobalScopes()->lockForUpdate()->get() as $cuota) {
                /** @var CuotaAdministracion $cuota */
                $devolver = (int) $cuota->pivot->valor;
                $cuota->forceFill(['pagado' => max(0, (int) $cuota->pagado - $devolver)])->save();
            }

            $pago->forceFill([
                'anulado_at' => now(),
                'motivo_anulacion' => mb_substr(trim($motivo), 0, 255),
                'anulado_por' => $userId,
            ])->save();

            return $pago;
        });
    }
}
