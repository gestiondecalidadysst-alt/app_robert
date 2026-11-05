<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Enums\ModuloEmpresa;
use App\Filament\App\Gerencia\UsaIndicadores;
use App\Filament\App\Resources\ManifiestosCarga\ManifiestoCargaResource;
use App\Filament\App\Resources\RegistrosRndc\RegistroRndcResource;
use App\Filament\App\Resources\RemesasCarga\RemesaCargaResource;
use App\Support\Modulos;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Carga (RNDC) en el tablero de gerencia.
 *
 * Además del volumen, muestra los dos frentes por los que hoy sanciona la
 * Supertransporte: el saldo pagado fuera de plazo y los tiempos logísticos sin reportar.
 */
class ResumenCarga extends StatsOverviewWidget
{
    use UsaIndicadores;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 6;

    protected ?string $heading = 'Carga (RNDC)';

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    public static function canView(): bool
    {
        $empresa = Filament::getTenant();

        return $empresa
            && Modulos::activo($empresa->getKey(), ModuloEmpresa::Carga)
            && (auth()->user()?->can('ver_gerencia') ?? false)
            && (auth()->user()?->can('ver_manifiestos_carga') ?? false);
    }

    protected function getDescription(): ?string
    {
        return $this->indicadores()->etiquetaPeriodo();
    }

    protected function getStats(): array
    {
        $c = $this->seccion('carga');

        return [
            Stat::make('Manifiestos expedidos', $c['manifiestos'])
                ->description("{$c['remesas']} remesas · {$c['toneladas']} t movilizadas")
                ->icon('heroicon-o-truck')->color('primary')
                ->url(ManifiestoCargaResource::getUrl()),

            Stat::make('Flete expedido', '$ '.number_format($c['flete'], 0, ',', '.'))
                ->description('Saldo pendiente: $ '.number_format($c['saldo_pendiente'], 0, ',', '.'))
                ->icon('heroicon-o-banknotes')->color('success')
                ->url(ManifiestoCargaResource::getUrl()),

            Stat::make('Saldos fuera de plazo', $c['saldo_vencido'])
                ->description($c['saldo_vencido']
                    ? '$ '.number_format($c['saldo_vencido_valor'], 0, ',', '.').' pasados los 5 días hábiles'
                    : 'Todos dentro de los 5 días hábiles')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($c['saldo_vencido'] ? 'danger' : 'success')
                ->url(ManifiestoCargaResource::getUrl('index', ['filters' => ['saldo_vencido' => ['value' => 'vencido']]])),

            Stat::make('Tiempos logísticos', "{$c['tiempos_completos']} de {$c['remesas']}")
                ->description($c['tiempos_incompletos']
                    ? "{$c['tiempos_incompletos']} remesas sin reportar completo"
                    : 'Todas las remesas al día')
                ->icon('heroicon-o-clock')
                ->color($c['tiempos_incompletos'] ? 'warning' : 'success')
                ->url(RemesaCargaResource::getUrl()),

            Stat::make('Documentos en regla ante el RNDC', $c['en_regla'])
                ->description($c['con_problema']
                    ? "{$c['rndc']['error']} con errores · {$c['rndc']['contingencia']} en espera"
                    : 'Sin errores ni envíos en espera')
                ->icon('heroicon-o-shield-check')
                ->color($c['con_problema'] ? 'danger' : 'success')
                ->url(RegistroRndcResource::getUrl()),

            Stat::make('Manifiestos sin cumplir', $c['sin_cumplir'])
                ->description("{$c['cumplidos']} cumplidos en el periodo")
                ->icon('heroicon-o-clipboard-document-check')
                ->color($c['sin_cumplir'] ? 'warning' : 'success')
                ->url(ManifiestoCargaResource::getUrl()),

            Stat::make('Anticipos entregados', '$ '.number_format($c['anticipos'], 0, ',', '.'))
                ->description('Del flete expedido en el periodo')
                ->icon('heroicon-o-arrow-up-tray')->color('gray'),
        ];
    }
}
