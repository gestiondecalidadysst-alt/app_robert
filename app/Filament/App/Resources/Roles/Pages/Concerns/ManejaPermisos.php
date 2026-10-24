<?php

namespace App\Filament\App\Resources\Roles\Pages\Concerns;

use App\Filament\App\Resources\Roles\RolResource;
use App\Models\Rol;
use App\Support\Permisos;
use Spatie\Permission\PermissionRegistrar;

/**
 * Traduce entre la matriz de casillas del formulario y los permisos del rol.
 */
trait ManejaPermisos
{
    /** @var array<int, string> */
    protected array $permisosSeleccionados = [];

    protected function separarPermisos(array $data): array
    {
        $this->permisosSeleccionados = [];

        foreach (array_keys(Permisos::agrupados()) as $grupo) {
            $campo = RolResource::campoPermisos($grupo);
            $this->permisosSeleccionados = [...$this->permisosSeleccionados, ...($data[$campo] ?? [])];
            unset($data[$campo]);
        }

        $this->permisosSeleccionados = array_values(array_intersect($this->permisosSeleccionados, Permisos::todos()));

        return $data;
    }

    protected function llenarPermisos(Rol $rol, array $data): array
    {
        $actuales = $rol->permissions()->pluck('name')->all();

        foreach (Permisos::agrupados() as $grupo => $opciones) {
            $data[RolResource::campoPermisos($grupo)] = array_values(array_intersect(array_keys($opciones), $actuales));
        }

        return $data;
    }

    protected function guardarPermisos(Rol $rol): void
    {
        // El rol Administrador conserva siempre todos los permisos de configuración de roles y usuarios.
        if ($rol->name === Rol::ADMINISTRADOR) {
            $this->permisosSeleccionados = array_values(array_unique([
                ...$this->permisosSeleccionados,
                'ver_roles', 'editar_roles', 'ver_usuarios', 'editar_usuarios',
            ]));
        }

        $rol->syncPermissions($this->permisosSeleccionados);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
