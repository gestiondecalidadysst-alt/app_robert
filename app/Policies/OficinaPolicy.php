<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class OficinaPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'oficinas';
}
