<?php

namespace App\Filament\App\Resources\Conductores\Pages;

use App\Filament\App\Resources\Conductores\ConductorResource;
use Filament\Resources\Pages\CreateRecord;

class CrearConductor extends CreateRecord
{
    protected static string $resource = ConductorResource::class;

    protected static ?string $title = 'Nuevo conductor';

    /** Después de crear se abre la hoja de vida, para registrar la licencia y la seguridad social. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
