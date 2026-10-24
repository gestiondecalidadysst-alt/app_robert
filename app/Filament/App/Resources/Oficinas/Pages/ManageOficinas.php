<?php

namespace App\Filament\App\Resources\Oficinas\Pages;

use App\Filament\App\Resources\Oficinas\OficinaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOficinas extends ManageRecords
{
    protected static string $resource = OficinaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
