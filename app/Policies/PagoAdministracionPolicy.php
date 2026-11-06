<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class PagoAdministracionPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'pagos_administracion';
}
