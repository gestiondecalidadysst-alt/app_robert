<?php

namespace App\Filament\Admin\Resources\NivelesAcademicos\Pages;

use App\Filament\Admin\Resources\NivelesAcademicos\NivelAcademicoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarNivelesAcademicos extends ManageRecords
{
    protected static string $resource = NivelAcademicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
