<?php

namespace App\Filament\App\Resources\RegistrosRndc\Pages;

use App\Filament\App\Resources\RegistrosRndc\RegistroRndcResource;
use Filament\Resources\Pages\ListRecords;

class ListarRegistrosRndc extends ListRecords
{
    protected static string $resource = RegistroRndcResource::class;

    protected ?string $subheading = 'Cada envío al RNDC con su número de radicado o los errores que devolvió.';
}
