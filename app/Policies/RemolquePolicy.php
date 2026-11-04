<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class RemolquePolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'remolques';
}
