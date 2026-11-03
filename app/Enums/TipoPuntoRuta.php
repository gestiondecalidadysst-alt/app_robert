<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Puntos de un rutograma. */
enum TipoPuntoRuta: string implements HasLabel
{
    case Critico = 'critico';
    case Parada = 'parada';
    case Control = 'control';
    case Emergencia = 'emergencia';

    public function getLabel(): string
    {
        return match ($this) {
            self::Critico => 'Punto crítico',
            self::Parada => 'Parada segura',
            self::Control => 'Punto de control / reporte',
            self::Emergencia => 'Apoyo en emergencia',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Critico => '⚠',
            self::Parada => 'P',
            self::Control => '✓',
            self::Emergencia => '+',
        };
    }
}
