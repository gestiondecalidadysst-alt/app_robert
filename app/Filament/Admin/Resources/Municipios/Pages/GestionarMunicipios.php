<?php

namespace App\Filament\Admin\Resources\Municipios\Pages;

use App\Filament\Admin\Resources\Municipios\MunicipioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarMunicipios extends ManageRecords
{
    protected static string $resource = MunicipioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
