<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Filament\App\Gerencia\UsaIndicadores;
use Filament\Widgets\Widget;

/** Principales clientes del periodo y costo de mantenimiento por vehículo. */
class TablasGerencia extends Widget
{
    use UsaIndicadores;

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.gerencia.tablas';

    protected function getViewData(): array
    {
        return [
            'clientes' => $this->seccion('operacion')['clientes'],
            'vehiculos' => $this->seccion('mantenimiento')['por_vehiculo'],
            'periodo' => $this->indicadores()->etiquetaPeriodo(),
        ];
    }
}
