<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Objeto del contrato de transporte especial (categorías de la Resolución 6652 de 2019).
 */
enum ObjetoContrato: string implements HasLabel
{
    case Escolar = 'escolar';
    case Empresarial = 'empresarial';
    case Salud = 'salud';
    case Turismo = 'turismo';
    case GrupoEspecifico = 'grupo_especifico';

    public function getLabel(): string
    {
        return match ($this) {
            self::Escolar => 'Transporte escolar',
            self::Empresarial => 'Transporte empresarial',
            self::Salud => 'Usuarios del servicio de salud',
            self::Turismo => 'Transporte de turistas',
            self::GrupoEspecifico => 'Grupo específico de usuarios',
        };
    }
}
