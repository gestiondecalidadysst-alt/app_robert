<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado de un servicio del planeador. El extracto (FUEC) y el seguimiento del conductor son
 * opcionales: un servicio puede quedarse en «planificado» o «con extracto».
 */
enum EstadoServicio: string implements HasColor, HasLabel
{
    case Planificado = 'planificado';
    case ConExtracto = 'con_extracto';
    case EnCurso = 'en_curso';
    case Finalizado = 'finalizado';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planificado => 'Planificado',
            self::ConExtracto => 'Con extracto',
            self::EnCurso => 'En curso',
            self::Finalizado => 'Finalizado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planificado => 'warning',
            self::ConExtracto => 'info',
            self::EnCurso => 'primary',
            self::Finalizado => 'success',
            self::Cancelado => 'gray',
        };
    }

    /** Color de la barra en el tablero. */
    public function hex(): string
    {
        return match ($this) {
            self::Planificado => '#b45309',
            self::ConExtracto => '#1d4ed8',
            self::EnCurso => '#c2410c',
            self::Finalizado => '#15803d',
            self::Cancelado => '#64748b',
        };
    }
}
