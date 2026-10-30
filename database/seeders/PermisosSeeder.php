<?php

namespace Database\Seeders;

use App\Actions\Configuracion\CrearEmpresa;
use App\Actions\Configuracion\SincronizarPermisos;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

/**
 * Crea los permisos definidos en App\Support\Permisos que falten y completa los roles por defecto
 * de cada empresa. Los roles existentes solo reciben los permisos nuevos: los ajustes que cada
 * empresa haya hecho en sus roles se conservan. Corre en cada despliegue.
 */
class PermisosSeeder extends Seeder
{
    public function run(): void
    {
        $nuevos = app(SincronizarPermisos::class)();

        Empresa::query()->each(fn (Empresa $empresa) => app(CrearEmpresa::class)->crearRoles($empresa, $nuevos));
    }
}
