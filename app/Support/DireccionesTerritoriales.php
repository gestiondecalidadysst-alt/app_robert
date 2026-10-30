<?php

namespace App\Support;

/**
 * Códigos de las Direcciones Territoriales del Ministerio de Transporte que forman los
 * 3 primeros dígitos del número del FUEC (instructivo del reverso del formato).
 */
final class DireccionesTerritoriales
{
    public const CODIGOS = [
        '305' => 'Antioquia - Chocó',
        '208' => 'Atlántico',
        '213' => 'Bolívar - San Andrés y Providencia',
        '415' => 'Boyacá - Casanare',
        '317' => 'Caldas',
        '319' => 'Cauca',
        '220' => 'Cesar',
        '223' => 'Córdoba - Sucre',
        '425' => 'Cundinamarca',
        '241' => 'Guajira',
        '441' => 'Huila - Caquetá',
        '247' => 'Magdalena',
        '550' => 'Meta - Vaupés - Vichada',
        '352' => 'Nariño - Putumayo',
        '454' => 'Norte de Santander - Arauca',
        '363' => 'Quindío',
        '366' => 'Risaralda',
        '468' => 'Santander',
        '473' => 'Tolima',
        '376' => 'Valle del Cauca',
    ];

    /** Opciones para un selector: "223 · Córdoba - Sucre". */
    public static function opciones(): array
    {
        return collect(self::CODIGOS)->sortBy(fn ($nombre) => $nombre)
            ->mapWithKeys(fn ($nombre, $codigo) => [$codigo => "{$codigo} · {$nombre}"])
            ->all();
    }

    public static function nombre(?string $codigo): ?string
    {
        return self::CODIGOS[$codigo] ?? null;
    }
}
