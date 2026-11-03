<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Cómo se asigna una plantilla de plan de mantenimiento a los vehículos.
 */
enum CriterioPlantilla: string implements HasColor, HasLabel
{
    case Clase = 'clase';
    case MarcaLinea = 'marca_linea';
    case Libre = 'libre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Clase => 'Por clase de vehículo',
            self::MarcaLinea => 'Por marca y línea',
            self::Libre => 'Libre (se asigna a mano)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Clase => 'info',
            self::MarcaLinea => 'info',
            self::Libre => 'gray',
        };
    }
}
