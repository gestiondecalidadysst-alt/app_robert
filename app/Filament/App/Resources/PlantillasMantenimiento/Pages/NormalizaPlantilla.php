<?php

namespace App\Filament\App\Resources\PlantillasMantenimiento\Pages;

use App\Enums\CriterioPlantilla;
use App\Models\PlantillaMantenimiento;

/** Normaliza los datos de la plantilla según el criterio y deja una sola por defecto. */
trait NormalizaPlantilla
{
    protected function normalizar(array $data): array
    {
        $criterio = $data['criterio'] instanceof CriterioPlantilla ? $data['criterio'] : CriterioPlantilla::from($data['criterio']);

        if ($criterio !== CriterioPlantilla::Clase) {
            $data['clase_vehiculo_id'] = null;
        }
        if ($criterio !== CriterioPlantilla::MarcaLinea) {
            $data['marca_id'] = null;
            $data['linea_id'] = null;
        }
        return $data;
    }

    protected function unicaPorDefecto(PlantillaMantenimiento $plantilla): void
    {
        if ($plantilla->por_defecto) {
            PlantillaMantenimiento::query()->whereKeyNot($plantilla->id)->update(['por_defecto' => false]);
        }
    }
}
