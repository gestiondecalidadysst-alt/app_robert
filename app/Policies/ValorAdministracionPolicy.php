<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class ValorAdministracionPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'valores_administracion';
}
