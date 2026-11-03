<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Filament\App\Gerencia\UsaIndicadores;
use Filament\Widgets\ChartWidget;

class CarteraPorEdades extends ChartWidget
{
    use UsaIndicadores;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 8;

    protected ?string $heading = 'Cartera de contratos por edades (a hoy)';

    protected ?string $description = 'Días vencidos desde la fecha límite de pago (o el fin del contrato).';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $e = $this->seccion('cartera')['edades'];

        return [
            'labels' => ['Sin vencer', '1–30 días', '31–60 días', '61–90 días', 'Más de 90 días'],
            'datasets' => [[
                'label' => 'Saldo',
                'data' => array_values($e),
                'backgroundColor' => ['#15803d', '#ca8a04', '#ea580c', '#dc2626', '#7f1d1d'],
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]]];
    }
}
