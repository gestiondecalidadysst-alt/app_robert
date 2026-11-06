<?php

namespace App\Filament\App\Widgets;

use App\Support\Alertas\CentroAlertas as Alertas;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Inicio: bandeja unificada de lo que requiere atención (documentos de vehículos y conductores
 * por vencer o vencidos, y administración en mora). Cada tarjeta lleva a su listado.
 */
class CentroAlertas extends StatsOverviewWidget
{
    protected static ?int $sort = -30;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = ['md' => 2, 'xl' => 3];

    protected ?string $heading = 'Alertas';

    protected ?string $description = 'Lo que requiere atención ahora.';

    protected function getPollingInterval(): ?string
    {
        return null;
    }

    public static function canView(): bool
    {
        $usuario = Filament::auth()->user();

        return (bool) ($usuario?->can('ver_documentos_vehiculo')
            || $usuario?->can('ver_documentos_conductor')
            || $usuario?->can('ver_pagos_administracion'));
    }

    protected function getStats(): array
    {
        $empresa = Filament::getTenant();
        $usuario = Filament::auth()->user();

        if (! $empresa || ! $usuario) {
            return [];
        }

        $grupos = Alertas::grupos($empresa, $usuario);

        if ($grupos === []) {
            return [
                Stat::make('Todo al día', 'Sin alertas')
                    ->description('No hay documentos por vencer ni administración en mora.')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->icon('heroicon-o-check-circle')
                    ->color('success'),
            ];
        }

        return array_map(fn (array $g) => Stat::make($g['titulo'], $g['total'])
            ->description($g['color'] === 'danger' ? 'Hay vencidos o en mora' : 'Por vencer pronto')
            ->descriptionIcon($g['color'] === 'danger' ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-clock')
            ->icon($g['icono'])
            ->color($g['color'])
            ->url($g['url']), $grupos);
    }
}
