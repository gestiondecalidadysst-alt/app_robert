<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class DocumentoVehiculoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'documentos_vehiculo';
}
