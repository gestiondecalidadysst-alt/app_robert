<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MetodoPago: string implements HasLabel
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Tarjeta = 'tarjeta';
    case Otro = 'otro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Tarjeta => 'Tarjeta',
            self::Otro => 'Otro',
        };
    }
}
