<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class NovedadVehiculoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'novedades_vehiculo';
}
