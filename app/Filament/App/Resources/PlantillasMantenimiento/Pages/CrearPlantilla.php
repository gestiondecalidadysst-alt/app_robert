<?php

namespace App\Filament\App\Resources\PlantillasMantenimiento\Pages;

use App\Filament\App\Resources\PlantillasMantenimiento\PlantillaMantenimientoResource;
use Filament\Resources\Pages\CreateRecord;

class CrearPlantilla extends CreateRecord
{
    use NormalizaPlantilla;

    protected static string $resource = PlantillaMantenimientoResource::class;

    protected static ?string $title = 'Nueva plantilla de plan';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizar($data);
    }

    protected function afterCreate(): void
    {
        $this->unicaPorDefecto($this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
