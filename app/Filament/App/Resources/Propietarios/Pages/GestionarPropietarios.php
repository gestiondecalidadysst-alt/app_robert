<?php

namespace App\Filament\App\Resources\Propietarios\Pages;

use App\Filament\App\Resources\Propietarios\PropietarioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarPropietarios extends ManageRecords
{
    protected static string $resource = PropietarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo propietario'),
        ];
    }
}
