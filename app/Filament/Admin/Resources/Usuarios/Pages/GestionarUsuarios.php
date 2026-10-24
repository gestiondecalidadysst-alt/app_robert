<?php

namespace App\Filament\Admin\Resources\Usuarios\Pages;

use App\Filament\Admin\Resources\Usuarios\UsuarioPlataformaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarUsuarios extends ManageRecords
{
    protected static string $resource = UsuarioPlataformaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
