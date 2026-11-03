<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class ServicioPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'servicios';
}
