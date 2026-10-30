<?php

namespace App\Filament\App\Resources\Vencimientos\Widgets;

use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Filament\App\Resources\Vencimientos\VencimientoResource;
use App\Models\Vehiculo;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Contadores de la pantalla de vencimientos.
 */
class ResumenVencimientos extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $dias = (int) config('ruta.dias_aviso_vencimiento');
        $vencidos = VencimientoResource::consultaBase()->vencidos()->count();
        $porVencer = VencimientoResource::consultaBase()->porVencer()->count();
        $vehiculosBloqueados = VehiculoResource::conDocumentosVencidosOFaltantes(Vehiculo::query()->enFlota())->count();

        return [
            Stat::make('Documentos vencidos', $vencidos)
                ->color($vencidos ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-circle'),
            Stat::make('Por vencer', $porVencer)
                ->description("{$dias} días antes (10 la preventiva)")
                ->color($porVencer ? 'warning' : 'success')
                ->icon('heroicon-o-clock'),
            Stat::make('Vehículos sin poder operar por documentos', $vehiculosBloqueados)
                ->description('Vencidos o sin registrar · ver vehículos')
                ->color($vehiculosBloqueados ? 'danger' : 'success')
                ->icon('heroicon-o-truck')
                ->url(VehiculoResource::getUrl('index', ['filters' => ['documentos' => ['value' => 'con_problemas']]])),
        ];
    }
}
