<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class DocumentoConductorPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'documentos_conductor';
}
