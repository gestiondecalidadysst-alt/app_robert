<?php

namespace App\Filament\App\Resources\ManifiestosCarga\Pages;

use App\Filament\App\Resources\ManifiestosCarga\ManifiestoCargaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarManifiestos extends ListRecords
{
    protected static string $resource = ManifiestoCargaResource::class;

    protected ?string $subheading = 'Manifiesto electrónico con sus remesas, reportado al RNDC (proceso 4).';

    protected function getHeaderActions(): array
    {
        // Con url() va a la página del asistente en vez de abrirlo en un modal estrecho.
        return [CreateAction::make()->label('Nuevo manifiesto')->url(fn () => CrearManifiesto::getUrl())];
    }
}
