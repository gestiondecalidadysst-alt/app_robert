<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Filament\App\Gerencia\UsaIndicadores;
use App\Filament\App\Pages\Planeador;
use App\Filament\App\Resources\Contratos\ContratoResource;
use App\Filament\App\Resources\Fuecs\FuecResource;
use App\Filament\App\Resources\Servicios\ServicioResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenOperacion extends StatsOverviewWidget
{
    use UsaIndicadores;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected function getHeading(): ?string
    {
        return 'Operación · ' . $this->indicadores()->etiquetaPeriodo();
    }

    protected function getStats(): array
    {
        $o = $this->seccion('operacion');

        return [
            Stat::make('Extractos expedidos', $o['extractos'])
                ->description("{$o['extractos_anulados']} anulados · {$o['contratos_nuevos']} contratos nuevos")
                ->icon('heroicon-o-document-check')->color('primary')->url(FuecResource::getUrl())
                ->chart($this->seccion('serie')['extractos']),
            Stat::make('Servicios', $o['servicios'])
                ->description("{$o['servicios_con_extracto']} con extracto · {$o['servicios_sin_asignar']} sin vehículo o conductor")
                ->icon('heroicon-o-calendar-days')->color($o['servicios_sin_asignar'] ? 'warning' : 'primary')->url(Planeador::getUrl()),
            Stat::make('Uso de la flota', $o['uso_flota'] . ' %')
                ->description("{$o['vehiculos_con_servicio']} de {$o['vehiculos_operando']} vehículos operando con servicio")
                ->icon('heroicon-o-chart-pie')->color('info'),
            Stat::make('Salen en 48 h sin extracto', $o['proximos_sin_extracto'])
                ->icon('heroicon-o-exclamation-triangle')->color($o['proximos_sin_extracto'] ? 'warning' : 'success')
                ->url(ServicioResource::getUrl('index', ['filters' => ['estado' => ['values' => ['planificado']]]])),
        ];
    }
}
