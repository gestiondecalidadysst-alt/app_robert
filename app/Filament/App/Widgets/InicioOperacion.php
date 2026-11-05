<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Pages\Planeador;
use App\Filament\App\Resources\Contratos\ContratoResource;
use App\Filament\App\Resources\Fuecs\FuecResource;
use App\Models\Contrato;
use App\Models\Fuec;
use App\Models\Servicio;
use App\Support\Gerencia\Indicadores;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Inicio: lo operativo del día y del mes — contratos, extractos, servicios, cartera y recaudo
 * (abonos y pagos totales).
 */
class InicioOperacion extends StatsOverviewWidget
{
    protected static ?int $sort = -30;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = ['md' => 3];

    protected ?string $heading = 'Hoy en la operación';

    protected function getPollingInterval(): ?string
    {
        return null;
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('ver_contratos') ?? false;
    }

    protected function getDescription(): ?string
    {
        return ucfirst(today()->translatedFormat('l j \d\e F \d\e Y'));
    }

    protected function getStats(): array
    {
        $empresa = Filament::getTenant()->id;
        $mes = new Indicadores($empresa, today()->startOfMonth(), today()->endOfMonth());
        $cartera = $mes->cartera();
        $tipos = $mes->pagosPorTipo();
        $serie = $mes->serie();
        $hastaHoy = (int) today()->day;
        $p = fn ($v) => Indicadores::pesos($v);

        $vigentes = Contrato::query()->noAnulados()->whereDate('fecha_inicio', '<=', today())->whereDate('fecha_fin', '>=', today());
        $extractosHoy = Fuec::query()->noAnulados()->whereDate('fecha_inicio', '<=', today())->whereDate('fecha_fin', '>=', today());
        $servicios = Servicio::query()->vigentes()->entre(today(), today());

        return [
            Stat::make('Contratos vigentes', (clone $vigentes)->count())
                ->description('Por '.$p((clone $vigentes)->sum('valor')).' · '.Contrato::query()->noAnulados()->whereDate('created_at', today())->count().' elaborados hoy')
                ->descriptionIcon('heroicon-m-document-text')->color('info')
                ->url(ContratoResource::getUrl()),
            Stat::make('Extractos vigentes hoy', (clone $extractosHoy)->count())
                ->description(Fuec::query()->noAnulados()->whereDate('created_at', today())->count().' expedidos hoy · '.array_sum($serie['extractos']).' en el mes')
                ->descriptionIcon('heroicon-m-document-check')->color('primary')
                ->chart(array_slice($serie['extractos'], 0, $hastaHoy))
                ->url(FuecResource::getUrl()),
            Stat::make('Servicios de hoy', (clone $servicios)->count())
                ->description((clone $servicios)->whereNull('fuec_id')->count().' sin extracto')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color((clone $servicios)->whereNull('fuec_id')->exists() ? 'warning' : 'success')
                ->url(Planeador::getUrl()),
            Stat::make('Pendiente por cobrar', $p($cartera['cartera']))
                ->description("{$cartera['contratos_con_saldo']} contratos con saldo · vencido ".$p($cartera['cartera_vencida']))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($cartera['cartera_vencida'] > 0 ? 'danger' : 'warning')
                ->url(ContratoResource::getUrl('index', ['filters' => ['con_saldo' => ['value' => 1]]])),
            Stat::make('Recaudado este mes', $p($cartera['recaudado']))
                ->description('Hoy: '.$p($cartera['recaudo_hoy']))
                ->descriptionIcon('heroicon-m-banknotes')->color('success')
                ->chart(array_slice($serie['recaudo'], 0, $hastaHoy)),
            Stat::make('Pagos del mes', $tipos['abono']['n'] + $tipos['abono_final']['n'] + $tipos['total']['n'])
                ->description("{$tipos['abono']['n']} abonos · {$tipos['abono_final']['n']} abonos finales · {$tipos['total']['n']} pagos totales")
                ->descriptionIcon('heroicon-m-receipt-percent')->color('info'),
        ];
    }
}
