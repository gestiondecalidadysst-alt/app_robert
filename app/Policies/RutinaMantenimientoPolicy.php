<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;

class RutinaMantenimientoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'rutinas_mantenimiento';

    public function reorder(User $user): bool
    {
        return $this->puede($user, 'editar');
    }
}
