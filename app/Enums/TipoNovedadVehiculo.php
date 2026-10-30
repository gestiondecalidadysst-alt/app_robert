<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Novedades que cambian el estado de un vehículo.
 */
enum TipoNovedadVehiculo: string implements HasColor, HasLabel
{
    case Suspension = 'suspension';
    case Taller = 'taller';
    case Desvinculacion = 'desvinculacion';
    case Reactivacion = 'reactivacion';
    case Vinculacion = 'vinculacion';

    public function getLabel(): string
    {
        return match ($this) {
            self::Suspension => 'Suspensión del servicio',
            self::Taller => 'Ingreso a taller',
            self::Desvinculacion => 'Desvinculación',
            self::Reactivacion => 'Reactivación',
            self::Vinculacion => 'Vinculación',
        };
    }

    public function getColor(): string
    {
        return $this->estadoResultante()->getColor();
    }

    /** Estado en que queda el vehículo después de la novedad. */
    public function estadoResultante(): EstadoVehiculo
    {
        return match ($this) {
            self::Suspension => EstadoVehiculo::Suspendido,
            self::Taller => EstadoVehiculo::Taller,
            self::Desvinculacion => EstadoVehiculo::Desvinculado,
            self::Reactivacion, self::Vinculacion => EstadoVehiculo::Operando,
        };
    }

    /** Novedades que se pueden registrar desde cada estado. */
    public static function disponiblesDesde(EstadoVehiculo $estado): array
    {
        return match ($estado) {
            EstadoVehiculo::Operando => [self::Suspension, self::Taller, self::Desvinculacion],
            EstadoVehiculo::Suspendido => [self::Reactivacion, self::Taller, self::Desvinculacion],
            EstadoVehiculo::Taller => [self::Reactivacion, self::Suspension, self::Desvinculacion],
            EstadoVehiculo::Desvinculado => [self::Vinculacion],
        };
    }

    /** Pide fecha prevista de reactivación. */
    public function pideReactivacion(): bool
    {
        return $this === self::Suspension || $this === self::Taller;
    }
}
