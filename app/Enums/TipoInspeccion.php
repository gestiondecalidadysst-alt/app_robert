<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Preoperacional (alistamiento diario, lo hace el conductor) o inspección mensual (la hace la empresa).
 */
enum TipoInspeccion: string implements HasLabel
{
    case Preoperacional = 'preoperacional';
    case Mensual = 'mensual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Preoperacional => 'Preoperacional (diario)',
            self::Mensual => 'Inspección mensual',
        };
    }

    public function corto(): string
    {
        return match ($this) {
            self::Preoperacional => 'Preoperacional',
            self::Mensual => 'Inspección mensual',
        };
    }
}
