<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;

class OficinaPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'oficinas';
}
