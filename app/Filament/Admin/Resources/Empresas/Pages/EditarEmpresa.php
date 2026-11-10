<?php

namespace App\Filament\Admin\Resources\Empresas\Pages;

use App\Filament\Admin\Resources\Empresas\EmpresaResource;
use App\Filament\Admin\Widgets\ResumenEmpresa;
use Filament\Resources\Pages\EditRecord;

class EditarEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ResumenEmpresa::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 4;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
