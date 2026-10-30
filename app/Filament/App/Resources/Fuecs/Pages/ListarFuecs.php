<?php

namespace App\Filament\App\Resources\Fuecs\Pages;

use App\Filament\App\Resources\Fuecs\FuecResource;
use App\Models\Fuec;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarFuecs extends ListRecords
{
    protected static string $resource = FuecResource::class;

    public function getSubheading(): ?string
    {
        $vigentes = Fuec::query()->noAnulados()->whereDate('fecha_inicio', '<=', today())->whereDate('fecha_fin', '>=', today())->count();
        $hoy = Fuec::query()->whereDate('created_at', today())->count();

        return "{$vigentes} vigentes hoy · {$hoy} expedidos hoy";
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Expedir FUEC')->icon('heroicon-o-document-plus')];
    }
}
