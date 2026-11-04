<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class TerceroCargaPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'terceros_carga';
}
