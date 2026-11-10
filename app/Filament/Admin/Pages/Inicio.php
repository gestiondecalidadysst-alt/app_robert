<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\AltasEmpresasChart;
use App\Filament\Admin\Widgets\EstadoPlataforma;
use App\Filament\Admin\Widgets\ResumenPlataforma;
use App\Filament\Admin\Widgets\UltimasEmpresas;
use Filament\Pages\Dashboard;

class Inicio extends Dashboard
{
    protected static ?string $title = 'Plataforma';

    protected static ?string $navigationLabel = 'Inicio';

    public function getWidgets(): array
    {
        return [
            ResumenPlataforma::class,
            EstadoPlataforma::class,
            UltimasEmpresas::class,
            AltasEmpresasChart::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
