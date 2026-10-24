<?php

namespace App\Filament\App\Resources\ValoresAdministracion\Pages;

use App\Filament\App\Resources\ValoresAdministracion\ValorAdministracionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageValoresAdministracion extends ManageRecords
{
    protected static string $resource = ValorAdministracionResource::class;

    protected ?string $subheading = 'Cuota que paga cada vehículo afiliado según su clase. Al cambiar la tarifa, registre un valor nuevo con su fecha: el historial se conserva.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo valor'),
        ];
    }
}
