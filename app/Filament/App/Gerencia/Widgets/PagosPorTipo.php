<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;
use Filament\Support\RawJs;

class PagosPorTipo extends Grafica
{
    protected static ?int $sort = 11;

    protected ?string $heading = 'Recaudo por tipo de pago';

    public function getDescription(): ?string
    {
        return 'Abonos, abonos finales y pagos totales · '.$this->indicadores()->etiquetaPeriodo();
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $t = $this->seccion('pagosPorTipo');

        return [
            'labels' => ["Abonos ({$t['abono']['n']})", "Abonos finales ({$t['abono_final']['n']})", "Pagos totales ({$t['total']['n']})"],
            'datasets' => [Paleta::serieDona([$t['abono']['valor'], $t['abono_final']['valor'], $t['total']['valor']], [Paleta::AZUL, Paleta::NARANJA, Paleta::AGUA])],
        ];
    }

    protected function getOptions(): RawJs
    {
        return Paleta::enPesos(Paleta::dona());
    }
}
