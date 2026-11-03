<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;

class MantenimientosPorTipo extends Grafica
{
    protected static ?int $sort = 21;

    protected ?string $heading = 'Mantenimientos preventivos y correctivos';

    public function getDescription(): ?string
    {
        return $this->indicadores()->etiquetaPeriodo();
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $m = $this->seccion('mantenimiento');

        return [
            'labels' => ["Preventivos ({$m['preventivos']})", "Correctivos ({$m['correctivos']})"],
            'datasets' => [Paleta::serieDona([$m['preventivos'], $m['correctivos']], [Paleta::AZUL, Paleta::NARANJA])],
        ];
    }

    protected function getOptions(): array
    {
        return Paleta::dona();
    }
}
