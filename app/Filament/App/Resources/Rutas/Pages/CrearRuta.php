<?php

namespace App\Filament\App\Resources\Rutas\Pages;

use App\Filament\App\Resources\Rutas\RutaResource;
use Filament\Resources\Pages\CreateRecord;

class CrearRuta extends CreateRecord
{
    protected static string $resource = RutaResource::class;

    protected static ?string $title = 'Nuevo rutograma';

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
