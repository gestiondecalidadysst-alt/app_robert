<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class DocumentoContratoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'documentos_contrato';
}
