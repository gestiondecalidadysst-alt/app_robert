<?php

namespace App\Filament\App\Resources\PlantillasMantenimiento\Pages;

use App\Filament\App\Resources\PlantillasMantenimiento\PlantillaMantenimientoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarPlantillas extends ListRecords
{
    protected static string $resource = PlantillaMantenimientoResource::class;

    protected static ?string $title = 'Plantillas de plan de mantenimiento';

    public function getSubheading(): ?string
    {
        return 'Cada vehículo sigue la plantilla asignada o la que le corresponde (marca y línea → marca → clase → por defecto). En la hoja de vida se puede personalizar el plan de un vehículo.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva plantilla')];
    }
}
