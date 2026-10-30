<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class NovedadConductorPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'novedades_conductor';
}
