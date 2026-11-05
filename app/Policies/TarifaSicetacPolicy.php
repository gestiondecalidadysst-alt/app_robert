<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class TarifaSicetacPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'tarifas_sicetac';
}
