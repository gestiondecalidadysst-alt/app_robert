<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;

class TendenciaPreoperacional extends Grafica
{
    protected static ?int $sort = 17;

    protected ?string $heading = 'Cumplimiento del preoperacional (12 meses, %)';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $t = $this->seccion('tendencias');

        return ['labels' => $t['meses'], 'datasets' => [Paleta::serieLinea('% cumplimiento', $t['preoperacional'], Paleta::AGUA)]];
    }

    protected function getOptions(): array
    {
        $o = Paleta::barras(leyenda: false);
        $o['scales']['y']['max'] = 100;

        return $o;
    }
}
