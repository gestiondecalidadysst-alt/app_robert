<?php

namespace App\Actions\Configuracion;

use App\Support\Permisos;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea en base de datos los permisos definidos en App\Support\Permisos que aún no existan.
 */
class SincronizarPermisos
{
    public function __invoke(): void
    {
        foreach (Permisos::todos() as $nombre) {
            Permission::findOrCreate($nombre, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
