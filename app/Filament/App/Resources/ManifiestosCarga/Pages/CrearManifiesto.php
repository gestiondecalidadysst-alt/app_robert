<?php

namespace App\Filament\App\Resources\ManifiestosCarga\Pages;

use App\Filament\App\Resources\ManifiestosCarga\ManifiestoCargaResource;
use App\Filament\App\Resources\ManifiestosCarga\Pages\Concerns\AsociaRemesas;
use Filament\Resources\Pages\CreateRecord;

class CrearManifiesto extends CreateRecord
{
    use AsociaRemesas;

    protected static string $resource = ManifiestoCargaResource::class;

    /** El asistente trae su propio botón «Expedir manifiesto» en el último paso. */
    protected function getFormActions(): array
    {
        return [];
    }
}
