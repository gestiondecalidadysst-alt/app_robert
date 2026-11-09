<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado de una cotización. "Vencida" no es un estado guardado: se calcula cuando una cotización en
 * borrador o enviada pasa su fecha de validez (ver Cotizacion::vencida()).
 */
enum EstadoCotizacion: string implements HasColor, HasLabel
{
    case Borrador = 'borrador';
    case Enviada = 'enviada';
    case Aceptada = 'aceptada';
    case Rechazada = 'rechazada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Enviada => 'Enviada',
            self::Aceptada => 'Aceptada',
            self::Rechazada => 'Rechazada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Enviada => 'info',
            self::Aceptada => 'success',
            self::Rechazada => 'danger',
        };
    }
}
