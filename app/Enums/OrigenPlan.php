<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * De dónde salió el plan de acción.
 */
enum OrigenPlan: string implements HasColor, HasLabel
{
    case Preoperacional = 'preoperacional';
    case Inspeccion = 'inspeccion';
    case Preventivo = 'preventivo';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Preoperacional => 'Preoperacional',
            self::Inspeccion => 'Inspección mensual',
            self::Preventivo => 'Mantenimiento preventivo vencido',
            self::Manual => 'Registrado por la empresa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Preoperacional => 'gray',
            self::Inspeccion => 'gray',
            self::Preventivo => 'gray',
            self::Manual => 'gray',
        };
    }
}
