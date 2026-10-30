<?php

namespace App\Filament\App\Resources\Clientes\Pages;

use App\Filament\App\Resources\Clientes\ClienteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarClientes extends ManageRecords
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo cliente')];
    }
}
