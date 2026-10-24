<?php

namespace App\Filament\Admin\Resources\Departamentos\Pages;

use App\Filament\Admin\Resources\Departamentos\DepartamentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarDepartamentos extends ManageRecords
{
    protected static string $resource = DepartamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
