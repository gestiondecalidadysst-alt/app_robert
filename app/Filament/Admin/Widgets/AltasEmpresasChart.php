<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Empresa;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Altas de empresas por mes en los últimos 12 meses: muestra el ritmo de crecimiento de la plataforma.
 */
class AltasEmpresasChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return 'Altas de empresas (últimos 12 meses)';
    }

    protected function getData(): array
    {
        $meses = collect(range(11, 0))->map(fn (int $i) => Carbon::now()->startOfMonth()->subMonths($i));

        $datos = $meses->map(fn (Carbon $m) => Empresa::query()->withoutGlobalScopes()
            ->whereBetween('created_at', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])
            ->count());

        return [
            'datasets' => [[
                'label' => 'Empresas dadas de alta',
                'data' => $datos->all(),
                'backgroundColor' => 'rgba(21, 49, 42, 0.65)',
                'borderColor' => '#15312a',
            ]],
            'labels' => $meses->map(fn (Carbon $m) => $m->translatedFormat('M Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
