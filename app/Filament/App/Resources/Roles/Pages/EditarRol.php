<?php

namespace App\Filament\App\Resources\Roles\Pages;

use App\Filament\App\Resources\Roles\Pages\Concerns\ManejaPermisos;
use App\Filament\App\Resources\Roles\RolResource;
use App\Models\Rol;
use Filament\Resources\Pages\EditRecord;

class EditarRol extends EditRecord
{
    use ManejaPermisos;

    protected static string $resource = RolResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Rol $rol */
        $rol = $this->getRecord();

        return $this->llenarPermisos($rol, $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Rol $rol */
        $rol = $this->getRecord();

        if ($rol->name === Rol::ADMINISTRADOR) {
            unset($data['name']);
        }

        return $this->separarPermisos($data);
    }

    protected function afterSave(): void
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
