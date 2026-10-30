<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class PagoContratoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'pagos_contrato';
}
