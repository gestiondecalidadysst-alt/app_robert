<?php

namespace App\Actions\Operacion;

use App\Enums\EstadoCotizacion;
use App\Models\Contrato;
use App\Models\Cotizacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Convierte una cotización aceptada en un contrato, tomando su cliente, asunto y valor total.
 * Los datos propios del contrato que la cotización no tiene (fechas de vigencia, objeto, forma de
 * pago) los aporta quien convierte. Deja la cotización marcada como aceptada y ligada al contrato.
 */
class ConvertirCotizacion
{
    public function __invoke(Cotizacion $cotizacion, array $datos): Contrato
    {
        if ($cotizacion->anulada()) {
            throw ValidationException::withMessages(['estado' => 'La cotización está anulada.']);
        }
        if ($cotizacion->convertida()) {
            throw ValidationException::withMessages(['estado' => 'Esta cotización ya se convirtió en el contrato '.$cotizacion->contrato->numero_formateado.'.']);
        }
        if (blank($datos['cliente_id'] ?? null)) {
            throw ValidationException::withMessages(['cliente_id' => 'Elige el cliente para el contrato.']);
        }

        return DB::transaction(function () use ($cotizacion, $datos) {
            $ultimo = (int) Contrato::query()->withoutGlobalScopes()->where('empresa_id', $cotizacion->empresa_id)->max('numero');

            $contrato = new Contrato([
                'cliente_id' => $datos['cliente_id'],
                'objeto' => $datos['objeto'],
                'descripcion_objeto' => $cotizacion->asunto,
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
                'valor' => $cotizacion->total(),
                'forma_pago' => $datos['forma_pago'] ?? null,
                'observaciones' => 'Generado desde la cotización '.$cotizacion->numero_formateado.'.',
            ]);
            $contrato->empresa_id = $cotizacion->empresa_id;
            $contrato->numero = $ultimo + 1;
            $contrato->save();

            $cotizacion->forceFill([
                'estado' => EstadoCotizacion::Aceptada,
                'contrato_id' => $contrato->id,
            ])->save();

            return $contrato;
        });
    }
}
