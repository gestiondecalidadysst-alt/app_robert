<?php

namespace App\Filament\App\Resources\Servicios\Pages;

use App\Filament\App\Pages\Planeador;
use App\Filament\App\Resources\Servicios\ServicioResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarServicios extends ListRecords
{
    protected static string $resource = ServicioResource::class;

    protected static ?string $title = 'Servicios planificados';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tablero')->label('Ver tablero')->icon('heroicon-o-calendar-days')->color('gray')->url(Planeador::getUrl()),
            CreateAction::make()->label('Planificar servicio'),
        ];
    }
}
