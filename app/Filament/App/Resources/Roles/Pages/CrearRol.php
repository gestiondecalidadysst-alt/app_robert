<?php

namespace App\Filament\App\Resources\Roles\Pages;

use App\Filament\App\Resources\Roles\Pages\Concerns\ManejaPermisos;
use App\Filament\App\Resources\Roles\RolResource;
use App\Models\Rol;
use Filament\Resources\Pages\CreateRecord;

class CrearRol extends CreateRecord
{
    use ManejaPermisos;

    protected static string $resource = RolResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = 'web';

        return $this->separarPermisos($data);
    }

    protected function afterCreate(): void
    {
        /** @var Rol $rol */
        $rol = $this->getRecord();
        $this->guardarPermisos($rol);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
