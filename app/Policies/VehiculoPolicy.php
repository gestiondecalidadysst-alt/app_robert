<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class VehiculoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'vehiculos';
}
