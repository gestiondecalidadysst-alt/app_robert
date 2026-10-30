<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Situación de los comparendos (multas) del conductor según la última consulta en el SIMIT.
 */
enum EstadoComparendos: string implements HasColor, HasLabel
{
    case SinComparendos = 'sin_comparendos';
    case Pendiente = 'pendiente';
    case AcuerdoPago = 'acuerdo_pago';
    case CobroCoactivo = 'cobro_coactivo';
    case Embargo = 'embargo';

    public function getLabel(): string
    {
        return match ($this) {
            self::SinComparendos => 'Sin comparendos',
            self::Pendiente => 'Pendiente de pago',
            self::AcuerdoPago => 'Acuerdo de pago',
            self::CobroCoactivo => 'Cobro coactivo',
            self::Embargo => 'Embargo',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SinComparendos => 'success',
            self::AcuerdoPago => 'warning',
            default => 'danger',
        };
    }
}
