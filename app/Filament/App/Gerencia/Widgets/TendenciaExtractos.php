<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;

class TendenciaExtractos extends Grafica
{
    protected static ?int $sort = 16;

    protected ?string $heading = 'Extractos expedidos (12 meses)';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $t = $this->seccion('tendencias');

        return ['labels' => $t['meses'], 'datasets' => [Paleta::serieLinea('Extractos', $t['extractos'], Paleta::VIOLETA)]];
    }

    protected function getOptions(): array
    {
        return Paleta::barras(leyenda: false);
    }
}
