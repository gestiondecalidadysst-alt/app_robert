<?php

namespace App\Services\Rndc;

use App\Models\Empresa;

/**
 * Documento que, además de registrarse, puede anularse ante el RNDC
 * (remesa = proceso 9, manifiesto = proceso 32).
 *
 * El manual conserva la historia de todo lo registrado: una vez anulado un documento
 * no se puede volver a crear con el mismo consecutivo, y solo se anula si el viaje
 * no ha comenzado.
 */
interface AnulableRndc
{
    /** Proceso del web service: 9 remesa, 32 manifiesto. */
    public function procesoAnulacionRndc(): int;

    /**
     * Variables del diccionario para la anulación.
     *
     * @param  array<string, mixed>  $datos  motivo y, en el transbordo de una remesa, municipio y causa
     * @return array<string, string|int>
     */
    public function variablesAnulacionRndc(Empresa $empresa, array $datos): array;

    /**
     * Lo que impide anular ante el RNDC, según las reglas del manual.
     *
     * @param  array<string, mixed>  $datos
     * @return list<string>
     */
    public function pendientesAnulacionRndc(array $datos): array;
}
