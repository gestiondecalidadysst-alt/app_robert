<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;
use Filament\Support\RawJs;

class TendenciaFinanciera extends Grafica
{
    protected static ?int $sort = 15;

    protected ?string $heading = 'Recaudo frente a costo de mantenimiento (12 meses)';

    protected int|string|array $columnSpan = ['md' => 2];

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
                Paleta::serieBarras('Recaudo', $t['recaudo'], Paleta::AZUL),
                Paleta::serieBarras('Costo de mantenimiento', $t['costo_mantenimiento'], Paleta::NARANJA),
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return Paleta::enPesos(Paleta::barras());
    }
}
