<?php

namespace App\Filament\App\Resources\Cotizaciones\Pages;

use App\Filament\App\Resources\Cotizaciones\CotizacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarCotizaciones extends ListRecords
{
    protected static string $resource = CotizacionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva cotización')];
    }
}
