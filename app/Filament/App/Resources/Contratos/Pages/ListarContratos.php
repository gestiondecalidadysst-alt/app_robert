<?php

namespace App\Filament\App\Resources\Contratos\Pages;

use App\Filament\App\Resources\Contratos\ContratoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarContratos extends ListRecords
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo contrato')];
    }
}
