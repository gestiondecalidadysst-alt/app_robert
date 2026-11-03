<?php

namespace App\Policies;

use App\Policies\Concerns\AutorizaPorPermiso;

class PlantillaMantenimientoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'rutinas_mantenimiento';
}
