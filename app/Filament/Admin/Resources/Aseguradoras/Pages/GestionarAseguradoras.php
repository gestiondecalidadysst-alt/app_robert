<?php

namespace App\Filament\Admin\Resources\Aseguradoras\Pages;

use App\Filament\Admin\Resources\Aseguradoras\AseguradoraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarAseguradoras extends ManageRecords
{
    protected static string $resource = AseguradoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
