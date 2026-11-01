<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Apto si ningún ítem crítico falló. Un ítem no crítico que falla queda como novedad, sin bloquear.
 */
enum ResultadoInspeccion: string implements HasColor, HasLabel
{
    case Apto = 'apto';
    case NoApto = 'no_apto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Apto => 'Apto',
            self::NoApto => 'No apto',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Apto => 'success',
            self::NoApto => 'danger',
        };
    }
}
