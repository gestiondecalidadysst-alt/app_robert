<?php

namespace App\Actions\Configuracion;

use App\Support\Permisos;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea en base de datos los permisos definidos en App\Support\Permisos que aún no existan.
 * Devuelve los nombres de los permisos que se crearon en esta ejecución.
 */
class SincronizarPermisos
{
    /** @return list<string> */
    public function __invoke(): array
    {
        $existentes = Permission::query()->where('guard_name', 'web')->pluck('name')->all();
        $nuevos = array_values(array_diff(Permisos::todos(), $existentes));

        foreach ($nuevos as $nombre) {
            Permission::findOrCreate($nombre, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $nuevos;
    }
}
