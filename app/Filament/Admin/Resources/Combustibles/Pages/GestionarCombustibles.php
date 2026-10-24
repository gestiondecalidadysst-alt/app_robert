<?php

namespace App\Filament\Admin\Resources\Combustibles\Pages;

use App\Filament\Admin\Resources\Combustibles\CombustibleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarCombustibles extends ManageRecords
{
    protected static string $resource = CombustibleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
