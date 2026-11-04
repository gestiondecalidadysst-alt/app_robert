<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Tipos de consulta de tránsito (precio fijo por tipo, definido por la plataforma). */
enum TipoConsultaTransito: string implements HasLabel
{
    case ConductorComparendos = 'conductor_comparendos';
    case ConductorLicencia = 'conductor_licencia';
    case VehiculoMultas = 'vehiculo_multas';
    case VehiculoRunt = 'vehiculo_runt';

    public function getLabel(): string
    {
        return match ($this) {
            self::ConductorComparendos => 'Infracciones del conductor (SIMIT)',
            self::ConductorLicencia => 'Licencia del conductor (RUNT)',
            self::VehiculoMultas => 'Infracciones del vehículo (SIMIT)',
            self::VehiculoRunt => 'RUNT del vehículo (SOAT y técnico-mecánica)',
        };
    }

    public function corto(): string
    {
        return match ($this) {
            self::ConductorComparendos, self::VehiculoMultas => 'Comparendos',
            self::ConductorLicencia => 'Licencia',
            self::VehiculoRunt => 'RUNT',
        };
    }

    public function esDeConductor(): bool
    {
        return in_array($this, [self::ConductorComparendos, self::ConductorLicencia], true);
    }

    /** Créditos que cobra PlacApi por defecto (configurables en la plataforma). */
    public function creditosPorDefecto(): int
    {
        return match ($this) {
            self::ConductorComparendos => 2,
            default => 1,
        };
    }

    /** @return list<self> */
    public static function deConductor(): array
    {
        return [self::ConductorComparendos, self::ConductorLicencia];
    }

    /** @return list<self> */
    public static function deVehiculo(): array
    {
        return [self::VehiculoMultas, self::VehiculoRunt];
    }
}
