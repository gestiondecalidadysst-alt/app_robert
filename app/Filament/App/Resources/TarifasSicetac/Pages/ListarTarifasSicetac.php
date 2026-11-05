<?php

namespace App\Filament\App\Resources\TarifasSicetac\Pages;

use App\Filament\App\Resources\TarifasSicetac\TarifaSicetacResource;
use Filament\Resources\Pages\ListRecords;

class ListarTarifasSicetac extends ListRecords
{
    protected static string $resource = TarifaSicetacResource::class;

    protected ?string $subheading = 'Costos mínimos por ruta. El flete de un manifiesto no puede quedar por debajo.';
}
