<?php

namespace App\Filament\App\Resources\PlantillasContrato\Pages;

use App\Filament\App\Resources\PlantillasContrato\PlantillaContratoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarPlantillas extends ListRecords
{
    protected static string $resource = PlantillaContratoResource::class;

    protected static ?string $title = 'Plantilla de contrato';

    protected ?string $subheading = 'Cada cambio crea una versión nueva. Los contratos ya emitidos conservan el texto con el que se emitieron.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Editar plantilla (nueva versión)')->icon('heroicon-o-pencil-square')];
    }
}
