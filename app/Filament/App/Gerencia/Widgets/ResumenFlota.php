<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Filament\App\Gerencia\UsaIndicadores;
use App\Filament\App\Resources\Conductores\ConductorResource;
use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Filament\App\Resources\Vencimientos\VencimientoResource;
use App\Filament\App\Resources\VencimientosConductores\VencimientoConductorResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenFlota extends StatsOverviewWidget
{
    use UsaIndicadores;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected ?string $heading = 'Flota y conductores (a hoy)';

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected function getStats(): array
    {
        $f = $this->seccion('flota');

        return [
            Stat::make('Vehículos operando', "{$f['operando']} de {$f['vehiculos']}")
                ->description("{$f['suspendidos']} suspendidos · {$f['taller']} en taller")
                ->icon('heroicon-o-truck')->color('primary')->url(VehiculoResource::getUrl()),
            Stat::make('Sin poder operar por documentos', $f['sin_documentos'])
                ->description('Vencidos o sin registrar')
                ->icon('heroicon-o-no-symbol')->color($f['sin_documentos'] ? 'danger' : 'success')
                ->url(VehiculoResource::getUrl('index', ['filters' => ['documentos' => ['value' => 'con_problemas']]])),
            Stat::make('Documentos de vehículos', "{$f['docs_vencidos']} vencidos")
                ->description("{$f['docs_por_vencer']} por vencer")
                ->icon('heroicon-o-document-text')->color($f['docs_vencidos'] ? 'danger' : ($f['docs_por_vencer'] ? 'warning' : 'success'))
                ->url(VencimientoResource::getUrl()),
            Stat::make('Conductores activos', $f['conductores'])
                ->description("{$f['licencias_vencidas']} con documentos vencidos · {$f['licencias_por_vencer']} por vencer")
                ->icon('heroicon-o-identification')->color($f['licencias_vencidas'] ? 'danger' : 'success')
                ->url($f['licencias_vencidas'] || $f['licencias_por_vencer'] ? VencimientoConductorResource::getUrl() : ConductorResource::getUrl()),
        ];
    }
}
