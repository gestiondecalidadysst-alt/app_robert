<?php

namespace App\Filament\App\Widgets;

use App\Enums\ModuloEmpresa;
use App\Filament\App\Resources\ManifiestosCarga\ManifiestoCargaResource;
use App\Filament\App\Resources\RegistrosRndc\RegistroRndcResource;
use App\Filament\App\Resources\RemesasCarga\RemesaCargaResource;
use App\Models\ManifiestoCarga;
use App\Models\RemesaCarga;
use App\Support\Modulos;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Inicio: la carga del día y lo que está pendiente de atender.
 *
 * A diferencia del tablero de gerencia, que mira el periodo y la tendencia, aquí
 * solo va lo accionable ahora: viajes en curso, documentos atascados ante el RNDC,
 * tiempos sin reportar y saldos fuera de plazo. Cada número lleva a su listado.
 */
class InicioCarga extends StatsOverviewWidget
{
    protected static ?int $sort = -25;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected ?string $heading = 'Carga de hoy';

    protected function getPollingInterval(): ?string
    {
        return null;
    }

    public static function canView(): bool
    {
        $empresa = Filament::getTenant();

        return $empresa
            && Modulos::activo($empresa->getKey(), ModuloEmpresa::Carga)
            && (auth()->user()?->can('ver_manifiestos_carga') ?? false);
    }

    protected function getDescription(): ?string
    {
        return ucfirst(today()->translatedFormat('l j \d\e F \d\e Y'));
    }

    protected function getStats(): array
    {
        $vigentes = ManifiestoCarga::query()->whereNull('anulado_at')->whereDoesntHave('cumplido')
            ->with('cumplido')->get();

        $deHoy = ManifiestoCarga::query()->whereNull('anulado_at')
            ->whereDate('fecha_expedicion', today())->count();

        $sueltas = RemesaCarga::query()->whereNull('anulado_at')->whereNull('manifiesto_id')->count();

        // Lo atascado ante el Ministerio: con errores o esperando a que el RNDC vuelva.
        $atascados = ManifiestoCarga::query()->whereNull('anulado_at')->with('ultimoRegistroRndc')->get()
            ->filter(fn (ManifiestoCarga $m) => in_array($m->estadoRndc(), ['error', 'contingencia'], true))
            ->count();

        $sinTiempos = RemesaCarga::query()->whereNull('anulado_at')->whereNotNull('manifiesto_id')
            ->withCount('tiemposLogisticos')->get()
            ->filter(fn (RemesaCarga $r) => $r->tiempos_logisticos_count < 6)
            ->count();

        $vencidos = $vigentes->filter(fn (ManifiestoCarga $m) => $m->estadoPagoSaldo() === 'vencido');

        return [
            Stat::make('Viajes en curso', $vigentes->count())
                ->description($deHoy ? "{$deHoy} expedidos hoy" : 'Ninguno expedido hoy')
                ->icon('heroicon-o-truck')->color('primary')
                ->url(ManifiestoCargaResource::getUrl()),

            Stat::make('Remesas sin manifiesto', $sueltas)
                ->description($sueltas ? 'Esperan que se les expida el manifiesto' : 'Todas asociadas')
                ->icon('heroicon-o-document-text')
                ->color($sueltas ? 'warning' : 'success')
                ->url(RemesaCargaResource::getUrl('index', ['filters' => ['sin_manifiesto' => ['value' => true]]])),

            Stat::make('Atascados en el RNDC', $atascados)
                ->description($atascados ? 'Con errores o esperando al Ministerio' : 'Nada pendiente de radicar')
                ->icon('heroicon-o-signal-slash')
                ->color($atascados ? 'danger' : 'success')
                ->url(RegistroRndcResource::getUrl()),

            Stat::make('Tiempos sin reportar', $sinTiempos)
                ->description($sinTiempos ? 'Remesas en viaje con tiempos incompletos' : 'Tiempos al día')
                ->icon('heroicon-o-clock')
                ->color($sinTiempos ? 'warning' : 'success')
                ->url(RemesaCargaResource::getUrl()),

            Stat::make('Saldos por pagar fuera de plazo', $vencidos->count())
                ->description($vencidos->count()
                    ? '$ '.number_format($vencidos->sum(fn (ManifiestoCarga $m) => $m->saldo()), 0, ',', '.').' pasados los 5 días hábiles'
                    : 'Sin saldos vencidos')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($vencidos->count() ? 'danger' : 'success')
                ->url(ManifiestoCargaResource::getUrl('index', ['filters' => ['saldo_vencido' => ['value' => 'vencido']]])),
        ];
    }
}
