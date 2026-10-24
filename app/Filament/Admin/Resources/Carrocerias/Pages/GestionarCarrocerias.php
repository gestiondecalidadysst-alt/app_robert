<?php

namespace App\Filament\Admin\Resources\Carrocerias\Pages;

use App\Filament\Admin\Resources\Carrocerias\CarroceriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarCarrocerias extends ManageRecords
{
    protected static string $resource = CarroceriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
