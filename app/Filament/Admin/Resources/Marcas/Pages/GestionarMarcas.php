<?php

namespace App\Filament\Admin\Resources\Marcas\Pages;

use App\Filament\Admin\Resources\Marcas\MarcaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarMarcas extends ManageRecords
{
    protected static string $resource = MarcaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
