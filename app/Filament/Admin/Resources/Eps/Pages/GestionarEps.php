<?php

namespace App\Filament\Admin\Resources\Eps\Pages;

use App\Filament\Admin\Resources\Eps\EpsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarEps extends ManageRecords
{
    protected static string $resource = EpsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
