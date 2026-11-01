<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;
use Illuminate\Database\Eloquent\Model;

/** Los planes de acción no se borran: se cierran. "Editar" = gestionar (iniciar, verificar, cerrar, rechazar). */
class PlanAccionPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'planes_accion';

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
