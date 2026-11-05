<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;

class ExtractosPeriodo extends Grafica
{
    protected static ?int $sort = 14;

    public function getHeading(): ?string
    {
        return 'Extractos expedidos · '.$this->indicadores()->etiquetaPeriodo();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $s = $this->seccion('serie');

        return ['labels' => $s['etiquetas'], 'datasets' => [Paleta::serieBarras('Extractos', $s['extractos'], Paleta::VIOLETA)]];
    }

    protected function getOptions(): array
    {
        return Paleta::barras(leyenda: false);
    }
}
