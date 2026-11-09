<?php

namespace App\Filament\App\Resources\Cotizaciones\Pages;

use App\Filament\App\Resources\Cotizaciones\CotizacionResource;
use Filament\Resources\Pages\EditRecord;

class EditarCotizacion extends EditRecord
{
    protected static string $resource = CotizacionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
