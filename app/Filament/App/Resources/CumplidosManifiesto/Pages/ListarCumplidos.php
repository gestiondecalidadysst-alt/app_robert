<?php
namespace App\Filament\App\Resources\CumplidosManifiesto\Pages;
use App\Filament\App\Resources\CumplidosManifiesto\CumplidoManifiestoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListarCumplidos extends ListRecords
{
    protected static string $resource = CumplidoManifiestoResource::class;
    protected ?string $subheading = 'Cumplido del manifiesto (RNDC proceso 6). Antes de reportarlo, deben estar cumplidas todas sus remesas (proceso 5).';
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo cumplido')];
    }
}
