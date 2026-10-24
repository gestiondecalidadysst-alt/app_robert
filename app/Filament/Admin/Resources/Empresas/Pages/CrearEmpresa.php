<?php

namespace App\Filament\Admin\Resources\Empresas\Pages;

use App\Actions\Configuracion\CrearEmpresa as CrearEmpresaAction;
use App\Filament\Admin\Resources\Empresas\EmpresaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CrearEmpresa extends CreateRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $administrador = $data['administrador'] ?? [];

        return app(CrearEmpresaAction::class)(Arr::except($data, ['administrador']), $administrador);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
