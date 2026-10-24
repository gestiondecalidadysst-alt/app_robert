<?php

namespace App\Filament\Admin\Resources\Lineas\Pages;

use App\Filament\Admin\Resources\Lineas\LineaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarLineas extends ManageRecords
{
    protected static string $resource = LineaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
