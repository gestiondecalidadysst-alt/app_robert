<?php

namespace App\Filament\Admin\Resources\ClasesVehiculo\Pages;

use App\Filament\Admin\Resources\ClasesVehiculo\ClaseVehiculoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarClasesVehiculo extends ManageRecords
{
    protected static string $resource = ClaseVehiculoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
