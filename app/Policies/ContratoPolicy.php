<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class ContratoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'contratos';
}
