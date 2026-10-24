<?php

namespace Database\Seeders;

use App\Actions\Configuracion\CrearEmpresa;
use App\Actions\Configuracion\SincronizarPermisos;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

/**
 * Crea los permisos definidos en App\Support\Permisos y completa los roles por defecto de cada empresa.
 * Ejecutar después de agregar recursos nuevos: php artisan db:seed --class=PermisosSeeder
 */
class PermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(SincronizarPermisos::class)();

        Empresa::query()->each(fn (Empresa $empresa) => app(CrearEmpresa::class)->crearRoles($empresa));
    }
}
