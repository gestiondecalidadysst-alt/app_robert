<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Filament\App\Gerencia\UsaIndicadores;
use App\Filament\App\Resources\Contratos\ContratoResource;
use App\Support\Gerencia\Indicadores;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenCartera extends StatsOverviewWidget
{
    use UsaIndicadores;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected function getHeading(): ?string
    {
        return 'Comercial y cartera de contratos';
    }

    protected function getStats(): array
    {
        $c = $this->seccion('cartera');
        $p = fn ($v) => Indicadores::pesos($v);

        return [
            Stat::make('Contratado en el periodo', $p($c['contratado']))
                ->icon('heroicon-o-document-text')->color('primary')->url(ContratoResource::getUrl()),
            Stat::make('Recaudado en el periodo', $p($c['recaudado']))
                ->description('Hoy: ' . $p($c['recaudo_hoy']))
                ->icon('heroicon-o-banknotes')->color('success'),
            Stat::make('Cartera por cobrar (a hoy)', $p($c['cartera']))
                ->description("{$c['contratos_con_saldo']} contratos con saldo · vencida " . $p($c['cartera_vencida']))
                ->icon('heroicon-o-scale')->color($c['cartera_vencida'] > 0 ? 'danger' : 'success')
                ->url(ContratoResource::getUrl('index', ['filters' => ['con_saldo' => ['value' => 1]]])),
            Stat::make('Contratos que terminan en 15 días', $c['contratos_por_vencer'])
                ->icon('heroicon-o-clock')->color($c['contratos_por_vencer'] ? 'warning' : 'gray')->url(ContratoResource::getUrl()),
        ];
    }
}
