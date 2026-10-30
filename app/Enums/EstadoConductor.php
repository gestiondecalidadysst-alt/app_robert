<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado de un conductor. Solo cambia con App\Actions\Conductores\CambiarEstadoConductor,
 * que deja la novedad en el historial.
 */
enum EstadoConductor: string implements HasColor, HasIcon, HasLabel
{
    case Activo = 'activo';
    case Suspendido = 'suspendido';
    case Retirado = 'retirado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Suspendido => 'Suspendido',
            self::Retirado => 'Retirado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Activo => 'success',
            self::Suspendido => 'danger',
            self::Retirado => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Activo => 'heroicon-m-check-circle',
            self::Suspendido => 'heroicon-m-no-symbol',
            self::Retirado => 'heroicon-m-arrow-right-start-on-rectangle',
        };
    }
}
