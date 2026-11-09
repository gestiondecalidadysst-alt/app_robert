<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class CotizacionPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'cotizaciones';
}
