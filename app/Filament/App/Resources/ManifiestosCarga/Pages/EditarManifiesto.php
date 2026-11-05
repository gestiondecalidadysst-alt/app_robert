<?php

namespace App\Filament\App\Resources\ManifiestosCarga\Pages;

use App\Filament\App\Resources\ManifiestosCarga\ManifiestoCargaResource;
use App\Filament\App\Resources\ManifiestosCarga\Pages\Concerns\AsociaRemesas;
use Filament\Resources\Pages\EditRecord;

class EditarManifiesto extends EditRecord
{
    use AsociaRemesas;

    protected static string $resource = ManifiestoCargaResource::class;

    /** En edición el asistente también cierra con su propio botón; ocultamos los de la página. */
    protected function getFormActions(): array
    {
        return [];
    }
}
