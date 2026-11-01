<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado del plan de acción: abierto → en ejecución → por verificar (con evidencia) → cerrado; o rechazado (la evidencia no sirve, vuelve a ejecución).
 */
enum EstadoPlan: string implements HasColor, HasLabel
{
    case Abierto = 'abierto';
    case EnEjecucion = 'en_ejecucion';
    case PorVerificar = 'por_verificar';
    case Cerrado = 'cerrado';
    case Rechazado = 'rechazado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::EnEjecucion => 'En ejecución',
            self::PorVerificar => 'Por verificar',
            self::Cerrado => 'Cerrado',
            self::Rechazado => 'Rechazado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Abierto => 'danger',
            self::EnEjecucion => 'warning',
            self::PorVerificar => 'info',
            self::Cerrado => 'success',
            self::Rechazado => 'danger',
        };
    }

    /** Estados en los que el plan sigue pendiente. */
    public static function pendientes(): array
    {
        return [self::Abierto->value, self::EnEjecucion->value, self::PorVerificar->value, self::Rechazado->value];
    }

    public function pendiente(): bool
    {
        return $this !== self::Cerrado;
    }
}
