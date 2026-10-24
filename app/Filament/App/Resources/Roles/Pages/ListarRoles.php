<?php

namespace App\Filament\App\Resources\Roles\Pages;

use App\Filament\App\Resources\Roles\RolResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarRoles extends ListRecords
{
    protected static string $resource = RolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo rol'),
        ];
    }
}
