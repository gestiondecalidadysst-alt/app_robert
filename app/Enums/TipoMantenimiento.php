<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipo de mantenimiento.
 */
enum TipoMantenimiento: string implements HasColor, HasLabel
{
    case Preventivo = 'preventivo';
    case Correctivo = 'correctivo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Preventivo => 'Preventivo',
            self::Correctivo => 'Correctivo',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Preventivo => 'info',
            self::Correctivo => 'warning',
        };
    }
}
