<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;
use Illuminate\Database\Eloquent\Model;

class UserPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'usuarios';

    /**
     * Un superadministrador solo lo administra otro superadministrador: si no, el
     * administrador de una empresa podría apoderarse de la cuenta que ve todas.
     */
    public function update(User $user, Model $model): bool
    {
        return $this->noEsSuperadminAjeno($user, $model) && $this->puede($user, 'editar');
    }

    /** Nadie se elimina a sí mismo de la empresa. */
    public function delete(User $user, Model $model): bool
    {
        return $user->isNot($model)
            && $this->noEsSuperadminAjeno($user, $model)
            && $this->puede($user, 'eliminar');
    }

    private function noEsSuperadminAjeno(User $user, Model $model): bool
    {
        return ! $model->es_superadmin || $user->es_superadmin;
    }
}
