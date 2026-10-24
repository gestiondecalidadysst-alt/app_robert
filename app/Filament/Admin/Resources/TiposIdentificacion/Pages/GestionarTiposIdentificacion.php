<?php

namespace App\Filament\Admin\Resources\TiposIdentificacion\Pages;

use App\Filament\Admin\Resources\TiposIdentificacion\TipoIdentificacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarTiposIdentificacion extends ManageRecords
{
    protected static string $resource = TipoIdentificacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
