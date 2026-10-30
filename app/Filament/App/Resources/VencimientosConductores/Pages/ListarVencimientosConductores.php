<?php

namespace App\Filament\App\Resources\VencimientosConductores\Pages;

use App\Filament\App\Resources\VencimientosConductores\VencimientoConductorResource;
use Filament\Resources\Pages\ListRecords;

class ListarVencimientosConductores extends ListRecords
{
    protected static string $resource = VencimientoConductorResource::class;

    protected static ?string $title = 'Vencimientos de conductores';

    protected ?string $subheading = 'Documento vigente de cada conductor activo o suspendido. Licencia y seguridad social bloquean el FUEC; los demás son de control.';
}
