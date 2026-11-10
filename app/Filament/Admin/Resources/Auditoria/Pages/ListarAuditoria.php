<?php

namespace App\Filament\Admin\Resources\Auditoria\Pages;

use App\Filament\Admin\Resources\Auditoria\ActividadResource;
use Filament\Resources\Pages\ListRecords;

class ListarAuditoria extends ListRecords
{
    protected static string $resource = ActividadResource::class;

    protected ?string $subheading = 'Actividad de todas las empresas: quién creó, modificó o eliminó información y cuándo.';
}
