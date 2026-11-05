<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class EmpresaConvenioPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'empresas_convenio';
}
