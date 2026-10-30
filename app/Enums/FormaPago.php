<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FormaPago: string implements HasLabel
{
    case Contado = 'contado';
    case Credito = 'credito';

    public function getLabel(): string
    {
        return match ($this) {
            self::Contado => 'Contado',
            self::Credito => 'Crédito',
        };
    }
}
