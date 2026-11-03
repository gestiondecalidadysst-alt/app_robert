<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Filament\App\Gerencia\UsaIndicadores;
use Filament\Widgets\ChartWidget;

/** Base de las gráficas del tablero de gerencia (mismo alto y filtros de la página). */
abstract class Grafica extends ChartWidget
{
    use UsaIndicadores;

    protected ?string $maxHeight = '280px';
}
