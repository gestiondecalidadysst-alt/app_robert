<?php

namespace App\Filament\App\Resources\Rutas\Pages;

use App\Filament\App\Resources\Rutas\RutaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarRutas extends ListRecords
{
    protected static string $resource = RutaResource::class;

    protected static ?string $title = 'Rutogramas';

    public function getSubheading(): ?string
    {
        return 'Rutas frecuentes con sus puntos críticos, paradas seguras y velocidades (PESV, paso 15). Cada servicio toma la que corresponde a su origen y destino.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo rutograma')];
    }
}
