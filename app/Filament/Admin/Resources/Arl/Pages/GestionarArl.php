<?php

namespace App\Filament\Admin\Resources\Arl\Pages;

use App\Filament\Admin\Resources\Arl\ArlResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarArl extends ManageRecords
{
    protected static string $resource = ArlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
