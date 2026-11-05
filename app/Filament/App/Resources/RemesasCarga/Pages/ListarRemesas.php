<?php
namespace App\Filament\App\Resources\RemesasCarga\Pages;
use App\Filament\App\Resources\RemesasCarga\RemesaCargaResource;
use Filament\Resources\Pages\ListRecords;
class ListarRemesas extends ListRecords
{
    protected static string $resource = RemesaCargaResource::class;

    protected ?string $subheading = 'Documento oficial por cada carga. Se asocia a un manifiesto y se reporta al RNDC (proceso 3).';

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->label('Nueva remesa')];
    }
}
