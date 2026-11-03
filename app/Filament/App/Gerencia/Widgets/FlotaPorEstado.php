<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;

class FlotaPorEstado extends Grafica
{
    protected static ?int $sort = 10;

    protected ?string $heading = 'Flota por estado';

    protected ?string $description = 'Vehículos vinculados, a hoy.';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $f = $this->seccion('flota');

        return [
            'labels' => ["Operando ({$f['operando']})", "En taller ({$f['taller']})", "Suspendidos ({$f['suspendidos']})"],
            'datasets' => [Paleta::serieDona([$f['operando'], $f['taller'], $f['suspendidos']], [Paleta::BIEN, Paleta::ATENCION, Paleta::CRITICO])],
        ];
    }

    protected function getOptions(): array
    {
        return Paleta::dona();
    }
}
