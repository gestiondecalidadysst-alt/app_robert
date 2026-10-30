<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado operativo de un vehículo. Solo cambia con App\Actions\Flota\CambiarEstadoVehiculo,
 * que deja la novedad en el historial.
 */
enum EstadoVehiculo: string implements HasColor, HasIcon, HasLabel
{
    case Operando = 'operando';
    case Suspendido = 'suspendido';
    case Taller = 'taller';
    case Desvinculado = 'desvinculado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Operando => 'Operando',
            self::Suspendido => 'Suspendido',
            self::Taller => 'En taller',
            self::Desvinculado => 'Desvinculado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Operando => 'success',
            self::Suspendido => 'danger',
            self::Taller => 'warning',
            self::Desvinculado => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Operando => 'heroicon-m-check-circle',
            self::Suspendido => 'heroicon-m-no-symbol',
            self::Taller => 'heroicon-m-wrench-screwdriver',
            self::Desvinculado => 'heroicon-m-arrow-right-start-on-rectangle',
        };
    }

    /** Si cuenta como parte de la flota activa (todo menos desvinculado). */
    public function enFlota(): bool
    {
        return $this !== self::Desvinculado;
    }
}
