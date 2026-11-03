<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;
use Filament\Support\RawJs;

class CarteraPorCliente extends Grafica
{
    protected static ?int $sort = 19;

    protected ?string $heading = 'Pendiente por cobrar por cliente (a hoy)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $c = collect($this->seccion('carteraPorCliente'));

        return ['labels' => $c->pluck('cliente')->all(), 'datasets' => [Paleta::serieBarras('Saldo', $c->pluck('saldo')->all(), Paleta::NARANJA)]];
    }

    protected function getOptions(): RawJs
    {
        return Paleta::enPesos(Paleta::barras(horizontal: true, leyenda: false), horizontal: true);
    }
}
