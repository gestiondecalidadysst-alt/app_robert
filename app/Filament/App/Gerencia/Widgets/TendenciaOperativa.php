<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Filament\App\Gerencia\UsaIndicadores;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class TendenciaOperativa extends ChartWidget
{
    use UsaIndicadores;

    protected static ?int $sort = 7;

    protected ?string $heading = 'Extractos expedidos y cumplimiento del preoperacional (12 meses)';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $t = $this->seccion('tendencias');

        return [
            'labels' => $t['meses'],
            'datasets' => [
                ['label' => 'Extractos', 'data' => $t['extractos'], 'borderColor' => '#1d4ed8', 'backgroundColor' => '#1d4ed8', 'yAxisID' => 'y'],
                ['label' => '% preoperacional', 'data' => $t['preoperacional'], 'borderColor' => '#b45309', 'backgroundColor' => '#b45309', 'yAxisID' => 'y1', 'spanGaps' => true],
            ],
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'position' => 'left', 'ticks' => ['precision' => 0]],
                'y1' => ['beginAtZero' => true, 'max' => 100, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]],
            ],
        ];
    }
}
