<?php

namespace App\Filament\Admin\Resources\Empresas\Pages;

use App\Filament\Admin\Resources\Empresas\EmpresaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarEmpresas extends ListRecords
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva empresa'),
        ];
    }
}
