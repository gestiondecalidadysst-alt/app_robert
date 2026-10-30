<?php

namespace App\Actions\Operacion;

use App\Models\Contrato;
use App\Models\Empresa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crea un contrato con el siguiente consecutivo de la empresa. El bloqueo sobre la empresa
 * evita que dos personas obtengan el mismo número al mismo tiempo.
 */
class CrearContrato
{
    public function __invoke(Empresa $empresa, array $datos): Contrato
    {
        self::validarFechas($datos);

        return DB::transaction(function () use ($empresa, $datos): Contrato {
            Empresa::query()->whereKey($empresa->getKey())->lockForUpdate()->first();

            $contrato = new Contrato($datos);
            $contrato->empresa_id = $empresa->getKey();
            $contrato->numero = (int) Contrato::query()->deEmpresa($empresa)->max('numero') + 1;
            $contrato->user_id = auth()->id();
            $contrato->save();

            return $contrato;
        });
    }

    public static function validarFechas(array $datos): void
    {
        if (isset($datos['fecha_inicio'], $datos['fecha_fin'])
            && Carbon::parse($datos['fecha_fin'])->lt(Carbon::parse($datos['fecha_inicio']))) {
            throw ValidationException::withMessages(['fecha_fin' => 'La fecha de terminación no puede ser anterior al inicio.']);
        }
    }
}
