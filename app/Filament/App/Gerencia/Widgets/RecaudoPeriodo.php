<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;
use Filament\Support\RawJs;

class RecaudoPeriodo extends Grafica
{
    protected static ?int $sort = 13;

    public function getHeading(): ?string
    {
        return 'Recaudo de contratos · '.$this->indicadores()->etiquetaPeriodo();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $s = $this->seccion('serie');

        return ['labels' => $s['etiquetas'], 'datasets' => [Paleta::serieBarras('Recaudo', $s['recaudo'], Paleta::AZUL)]];
    }

    protected function getOptions(): RawJs
    {
        return Paleta::enPesos(Paleta::barras(leyenda: false));
    }
}
