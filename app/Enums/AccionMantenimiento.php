<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Acción realizada en el mantenimiento (como en la app anterior).
 */
enum AccionMantenimiento: string implements HasColor, HasLabel
{
    case Cambio = 'cambio';
    case Ajuste = 'ajuste';
    case Revision = 'revision';
    case Reparacion = 'reparacion';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cambio => 'Cambio',
            self::Ajuste => 'Ajuste',
            self::Revision => 'Revisión',
            self::Reparacion => 'Reparación',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cambio => 'gray',
            self::Ajuste => 'gray',
            self::Revision => 'gray',
            self::Reparacion => 'gray',
        };
    }
}
