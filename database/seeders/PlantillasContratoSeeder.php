<?php

namespace Database\Seeders;

use App\Actions\Configuracion\CrearEmpresa;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

/**
 * Da la plantilla base de contrato (versión 1) a las empresas que aún no tienen.
 * Corre en cada despliegue; no toca las plantillas existentes.
 */
class PlantillasContratoSeeder extends Seeder
{
    public function run(): void
    {
        Empresa::query()->each(fn (Empresa $empresa) => CrearEmpresa::crearPlantillaContrato($empresa));
    }
}
