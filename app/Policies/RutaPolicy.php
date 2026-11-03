<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class RutaPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'rutas';
}
