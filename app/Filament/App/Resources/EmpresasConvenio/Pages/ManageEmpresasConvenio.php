<?php

namespace App\Filament\App\Resources\EmpresasConvenio\Pages;

use App\Filament\App\Resources\EmpresasConvenio\EmpresaConvenioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmpresasConvenio extends ManageRecords
{
    protected static string $resource = EmpresaConvenioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
