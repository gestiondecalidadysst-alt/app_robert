<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado guardado de una actividad del programa. Vencida y próxima se calculan por fecha.
 */
enum EstadoActividad: string implements HasColor, HasLabel
{
    case Programada = 'programada';
    case Ejecutada = 'ejecutada';
    case Cancelada = 'cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Programada => 'Programada',
            self::Ejecutada => 'Ejecutada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Programada => 'info',
            self::Ejecutada => 'success',
            self::Cancelada => 'gray',
        };
    }
}
