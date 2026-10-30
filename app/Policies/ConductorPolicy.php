<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class ConductorPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'conductores';
}
