<?php

namespace App\Services\Rndc;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Registro maestro que se reporta al RNDC (tercero o vehículo).
 * variablesRndc() arma las variables del diccionario de datos; validarRndc() repite las validaciones
 * del manual para no enviar algo que el RNDC va a rechazar.
 */
interface RegistrableRndc
{
    /** Proceso del web service (11 terceros, 12 vehículos). */
    public function procesoRndc(): int;

    /** @return array<string, string|int> variable del diccionario => valor (sin vacíos) */
    public function variablesRndc(Empresa $empresa): array;

    /** @return list<string> errores que impiden registrar */
    public function validarRndc(): array;

    /** Nombre corto para pantallas y PDF (nombre y documento, o placa). */
    public function etiquetaRndc(): string;

    public function registrosRndc(): MorphMany;
}
