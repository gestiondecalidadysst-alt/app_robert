<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class AsignacionVehiculoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'asignaciones_vehiculo';
}
