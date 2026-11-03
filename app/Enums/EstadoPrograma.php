<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado del programa anual de mantenimiento: borrador (se puede regenerar) → aprobado (línea base) → reemplazado (por una versión nueva).
 */
enum EstadoPrograma: string implements HasColor, HasLabel
{
    case Borrador = 'borrador';
    case Aprobado = 'aprobado';
    case Reemplazado = 'reemplazado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Aprobado => 'Aprobado',
            self::Reemplazado => 'Reemplazado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Aprobado => 'success',
            self::Reemplazado => 'warning',
        };
    }
}
