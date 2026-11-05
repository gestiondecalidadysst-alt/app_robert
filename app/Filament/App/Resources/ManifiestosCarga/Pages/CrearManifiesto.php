<?php

namespace App\Filament\App\Resources\ManifiestosCarga\Pages;

use App\Filament\App\Resources\ManifiestosCarga\ManifiestoCargaResource;
use Filament\Resources\Pages\CreateRecord;

class CrearManifiesto extends CreateRecord
{
    protected static string $resource = ManifiestoCargaResource::class;

    /** El asistente trae su propio botón «Expedir manifiesto» en el último paso. */
    protected function getFormActions(): array
    {
        return [];
    }
}
