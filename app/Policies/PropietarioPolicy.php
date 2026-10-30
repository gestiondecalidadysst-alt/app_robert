<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class PropietarioPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'propietarios';
}
