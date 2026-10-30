<?php

namespace App\Filament\App\Resources\Vencimientos\Pages;

use App\Filament\App\Resources\Vencimientos\VencimientoResource;
use App\Filament\App\Resources\Vencimientos\Widgets\ResumenVencimientos;
use Filament\Resources\Pages\ListRecords;

class ListarVencimientos extends ListRecords
{
    protected static string $resource = VencimientoResource::class;

    protected ?string $subheading = 'Documento vigente de cada vehículo en flota. Renovar crea un registro nuevo y conserva el historial.';

    protected function getHeaderWidgets(): array
    {
        return [
            ResumenVencimientos::class,
        ];
    }
}
