<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Filament\App\Gerencia\UsaIndicadores;
use Filament\Widgets\ChartWidget;

class TendenciaFinanciera extends ChartWidget
{
    use UsaIndicadores;

    protected static ?int $sort = 6;

    protected ?string $heading = 'Recaudo de contratos y costo de mantenimiento (12 meses)';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $t = $this->seccion('tendencias');

        return [
            'labels' => $t['meses'],
            'datasets' => [
                ['label' => 'Recaudo', 'data' => $t['recaudo'], 'backgroundColor' => '#15803d'],
                ['label' => 'Costo de mantenimiento', 'data' => $t['costo_mantenimiento'], 'backgroundColor' => '#c2410c'],
            ],
        ];
    }
}
