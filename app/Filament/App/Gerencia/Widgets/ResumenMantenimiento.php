<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Filament\App\Gerencia\UsaIndicadores;
use App\Filament\App\Resources\Mantenimientos\MantenimientoResource;
use App\Filament\App\Resources\ProgramasMantenimiento\ProgramaMantenimientoResource;
use App\Support\Gerencia\Indicadores;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenMantenimiento extends StatsOverviewWidget
{
    use UsaIndicadores;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 5;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected ?string $heading = 'Mantenimiento';

    protected function getStats(): array
    {
        $m = $this->seccion('mantenimiento');

        return [
            Stat::make('Cumplimiento del programa', $m['programa_cumplimiento'] === null ? '—' : $m['programa_cumplimiento'] . ' %')
                ->description($m['programa'] ? "{$m['programa_ejecutadas']} de {$m['programa_exigibles']} exigibles a la fecha" : 'Sin programa del año')
                ->icon('heroicon-o-calendar')->color($m['programa_cumplimiento'] === null ? 'gray' : ($m['programa_cumplimiento'] >= 90 ? 'success' : 'warning'))
                ->url(ProgramaMantenimientoResource::getUrl()),
            Stat::make('Actividades vencidas', $m['actividades_vencidas'])
                ->icon('heroicon-o-exclamation-circle')->color($m['actividades_vencidas'] ? 'danger' : 'success')
                ->url(ProgramaMantenimientoResource::getUrl()),
            Stat::make('Preventivos / correctivos', "{$m['preventivos']} / {$m['correctivos']}")
                ->description('Mantenimientos del periodo')
                ->icon('heroicon-o-wrench-screwdriver')->color($m['correctivos'] > $m['preventivos'] ? 'warning' : 'success')
                ->url(MantenimientoResource::getUrl()),
            Stat::make('Costo de mantenimiento', Indicadores::pesos($m['costo']))
                ->description($m['costo_km'] ? Indicadores::pesos($m['costo_km']) . ' por km recorrido' : 'Sin km suficientes para el costo por km')
                ->icon('heroicon-o-currency-dollar')->color('gray'),
        ];
    }
}
