<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class CuotaAdministracionPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'pagos_administracion';
}
