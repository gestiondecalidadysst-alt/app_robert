<?php

namespace App\Filament\App\Resources\Auditoria\Pages;

use App\Filament\App\Resources\Auditoria\ActividadResource;
use Filament\Resources\Pages\ListRecords;

class ListarAuditoria extends ListRecords
{
    protected static string $resource = ActividadResource::class;

    protected ?string $subheading = 'Registro de quién creó, modificó o eliminó información y cuándo.';
}
