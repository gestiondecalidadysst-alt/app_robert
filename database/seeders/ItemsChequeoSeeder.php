<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Support\Mantenimiento\RutinasBase;
use App\Support\Pesv\ItemsBase;
use Illuminate\Database\Seeder;

/**
 * Da los ítems de chequeo por defecto (preoperacional e inspección mensual) y las rutinas preventivas
 * base a las empresas que aún no tienen. Corre en cada despliegue; no toca los ítems existentes.
 */
class ItemsChequeoSeeder extends Seeder
{
    public function run(): void
    {
        Empresa::query()->each(function (Empresa $empresa): void {
            ItemsBase::crear($empresa);
            RutinasBase::crear($empresa);
        });
    }
}
