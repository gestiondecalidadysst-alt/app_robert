<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class ClientePolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'clientes';
}
