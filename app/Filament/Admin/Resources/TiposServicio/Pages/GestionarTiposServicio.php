<?php

namespace App\Filament\Admin\Resources\TiposServicio\Pages;

use App\Filament\Admin\Resources\TiposServicio\TipoServicioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarTiposServicio extends ManageRecords
{
    protected static string $resource = TipoServicioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
