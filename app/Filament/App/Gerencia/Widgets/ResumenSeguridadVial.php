<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Filament\App\Gerencia\UsaIndicadores;
use App\Filament\App\Pages\Planeador;
use App\Filament\App\Resources\Inspecciones\InspeccionResource;
use App\Filament\App\Resources\PlanesAccion\PlanAccionResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenSeguridadVial extends StatsOverviewWidget
{
    use UsaIndicadores;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected ?string $heading = 'Seguridad vial';

    protected function getStats(): array
    {
        $s = $this->seccion('pesv');
        $pct = fn ($v) => $v === null ? '—' : $v . ' %';

        return [
            Stat::make('Preoperacional cumplido', $pct($s['preop_cumplimiento']))
                ->description("{$s['preop_cumplidos']} de {$s['preop_exigidos']} días con extracto · {$s['preop_no_aptos']} no aptos")
                ->icon('heroicon-o-clipboard-document-check')
                ->color($s['preop_cumplimiento'] === null ? 'gray' : ($s['preop_cumplimiento'] >= 95 ? 'success' : ($s['preop_cumplimiento'] >= 80 ? 'warning' : 'danger')))
                ->url(InspeccionResource::getUrl()),
            Stat::make('Planes de acción pendientes', $s['planes_pendientes'])
                ->description("{$s['planes_vencidos']} vencidos · {$s['planes_alta']} de prioridad alta")
                ->icon('heroicon-o-wrench')->color($s['planes_vencidos'] || $s['planes_alta'] ? 'danger' : ($s['planes_pendientes'] ? 'warning' : 'success'))
                ->url(PlanAccionResource::getUrl()),
            Stat::make('Servicios con avisos de jornada', $s['avisos_jornada'])
                ->icon('heroicon-o-clock')->color($s['avisos_jornada'] ? 'warning' : 'success')->url(Planeador::getUrl()),
            Stat::make('Planes de viaje leídos', $pct($s['planes_leidos']))
                ->description("{$s['novedades_ruta']} novedades reportadas en ruta")
                ->icon('heroicon-o-map')->color('info'),
        ];
    }
}
