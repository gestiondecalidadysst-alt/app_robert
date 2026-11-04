<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class RegistroRndcPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'rndc';
}
