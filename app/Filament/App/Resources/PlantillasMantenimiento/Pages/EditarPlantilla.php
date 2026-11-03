<?php

namespace App\Filament\App\Resources\PlantillasMantenimiento\Pages;

use App\Filament\App\Resources\PlantillasMantenimiento\PlantillaMantenimientoResource;
use Filament\Resources\Pages\EditRecord;

class EditarPlantilla extends EditRecord
{
    use NormalizaPlantilla;

    protected static string $resource = PlantillaMantenimientoResource::class;

    public function getSubheading(): ?string
    {
        $n = PlantillaMantenimientoResource::vehiculosQueLaSiguen($this->getRecord())->count();

        return "{$n} vehículo(s) siguen esta plantilla: los cambios les aplican de inmediato (los de plan personalizado no cambian).";
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizar($data);
    }

    protected function afterSave(): void
    {
        $this->unicaPorDefecto($this->getRecord());
    }
}
