<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class RemesaCargaPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'remesas_carga';
}
