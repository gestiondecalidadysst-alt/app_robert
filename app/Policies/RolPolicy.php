<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;

class RolPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'roles';
}
