<?php
namespace App\Filament\App\Resources\RemesasCarga\Pages;
use App\Filament\App\Resources\RemesasCarga\RemesaCargaResource;
use Filament\Resources\Pages\CreateRecord;
class CrearRemesa extends CreateRecord
{
    protected static string $resource = RemesaCargaResource::class;

    /** El wizard trae su propio botón «Expedir remesa» en el último paso. */
    protected function getFormActions(): array
    {
        return [];
    }
}
