<?php

namespace App\Filament\App\Resources\Rutas\Pages;

use App\Filament\App\Resources\Rutas\RutaResource;
use Filament\Resources\Pages\EditRecord;

class EditarRuta extends EditRecord
{
    protected static string $resource = RutaResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
