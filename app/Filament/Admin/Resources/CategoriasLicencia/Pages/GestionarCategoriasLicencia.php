<?php

namespace App\Filament\Admin\Resources\CategoriasLicencia\Pages;

use App\Filament\Admin\Resources\CategoriasLicencia\CategoriaLicenciaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarCategoriasLicencia extends ManageRecords
{
    protected static string $resource = CategoriaLicenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
