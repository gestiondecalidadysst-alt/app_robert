<?php

namespace App\Filament\App\Resources\Vehiculos\Pages;

use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use Filament\Resources\Pages\CreateRecord;

class CrearVehiculo extends CreateRecord
{
    protected static string $resource = VehiculoResource::class;

    protected static ?string $title = 'Nuevo vehículo';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EditarVehiculo::limpiarVinculacion($data);
    }

    /** Después de crear se abre la hoja de vida, para registrar los documentos. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
