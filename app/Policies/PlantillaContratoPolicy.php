<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class PlantillaContratoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'plantillas_contrato';
}
