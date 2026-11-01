<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Prioridad del plan de acción. Alta = falla crítica: el vehículo no queda apto hasta cerrarlo.
 */
enum PrioridadPlan: string implements HasColor, HasLabel
{
    case Alta = 'alta';
    case Media = 'media';
    case Baja = 'baja';

    public function getLabel(): string
    {
        return match ($this) {
            self::Alta => 'Alta',
            self::Media => 'Media',
            self::Baja => 'Baja',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Alta => 'danger',
            self::Media => 'warning',
            self::Baja => 'gray',
        };
    }
}
