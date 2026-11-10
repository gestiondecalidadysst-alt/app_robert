<?php

namespace App\Filament\Admin\Resources\Manual\Pages;

use App\Filament\Admin\Resources\Manual\SeccionManualResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarSeccionesManual extends ListRecords
{
    protected static string $resource = SeccionManualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva sección'),
        ];
    }
}
