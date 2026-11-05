<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;

class ServiciosPorVehiculo extends Grafica
{
    protected static ?int $sort = 20;

    public function getHeading(): ?string
    {
        return 'Días de servicio por vehículo · '.$this->indicadores()->etiquetaPeriodo();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $v = collect($this->seccion('serviciosPorVehiculo'));

        return ['labels' => $v->pluck('placa')->all(), 'datasets' => [Paleta::serieBarras('Días con servicio', $v->pluck('dias')->all(), Paleta::AGUA)]];
    }

    protected function getOptions(): array
    {
        return Paleta::barras(horizontal: true, leyenda: false);
    }
}
