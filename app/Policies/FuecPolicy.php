<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class FuecPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'fuecs';
}
