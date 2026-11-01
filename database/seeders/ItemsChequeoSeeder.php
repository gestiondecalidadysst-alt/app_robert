<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Support\Pesv\ItemsBase;
use Illuminate\Database\Seeder;

/**
 * Da los ítems de chequeo por defecto (preoperacional e inspección mensual) a las empresas que
 * aún no tienen. Corre en cada despliegue; no toca los ítems existentes.
 */
class ItemsChequeoSeeder extends Seeder
{
    public function run(): void
    {
        Empresa::query()->each(fn (Empresa $empresa) => ItemsBase::crear($empresa));
    }
}
