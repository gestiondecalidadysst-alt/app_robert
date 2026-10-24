<?php

namespace App\Filament\Admin\Resources\Empresas\Pages;

use App\Filament\Admin\Resources\Empresas\EmpresaResource;
use Filament\Resources\Pages\EditRecord;

class EditarEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
