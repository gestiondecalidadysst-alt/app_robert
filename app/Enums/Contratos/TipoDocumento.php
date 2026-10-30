<?php

namespace App\Enums\Contratos;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipo de documento con vencimiento (de vehículo o de conductor).
 * Lo implementan los enums TipoDocumentoVehiculo y TipoDocumentoConductor.
 */
interface TipoDocumento extends HasLabel
{
    /** Nombre corto para encabezados y tarjetas. */
    public function corto(): string;

    /** Si es obligatorio para operar (su ausencia o vencimiento bloquea). */
    public function obligatorio(): bool;

    /** Meses de vigencia habituales, para sugerir el vencimiento. */
    public function mesesVigencia(): ?int;

    /** Días antes del vencimiento en que pasa a "por vencer" (null = config('ruta.dias_aviso_vencimiento')). */
    public function diasAviso(): ?int;
}
