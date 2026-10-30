<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Novedades que cambian el estado de un conductor.
 */
enum TipoNovedadConductor: string implements HasColor, HasLabel
{
    case Suspension = 'suspension';
    case Retiro = 'retiro';
    case Reactivacion = 'reactivacion';
    case Reintegro = 'reintegro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Suspension => 'Suspensión',
            self::Retiro => 'Retiro',
            self::Reactivacion => 'Reactivación',
            self::Reintegro => 'Reintegro',
        };
    }

    public function getColor(): string
    {
        return $this->estadoResultante()->getColor();
    }

    public function estadoResultante(): EstadoConductor
    {
        return match ($this) {
            self::Suspension => EstadoConductor::Suspendido,
            self::Retiro => EstadoConductor::Retirado,
            self::Reactivacion, self::Reintegro => EstadoConductor::Activo,
        };
    }

    /** Novedades que se pueden registrar desde cada estado. */
    public static function disponiblesDesde(EstadoConductor $estado): array
    {
        return match ($estado) {
            EstadoConductor::Activo => [self::Suspension, self::Retiro],
            EstadoConductor::Suspendido => [self::Reactivacion, self::Retiro],
            EstadoConductor::Retirado => [self::Reintegro],
        };
    }

    public function pideReactivacion(): bool
    {
        return $this === self::Suspension;
    }
}
