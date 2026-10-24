<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;
use Illuminate\Database\Eloquent\Model;

class UserPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'usuarios';

    /** Nadie se elimina a sí mismo de la empresa. */
    public function delete(User $user, Model $model): bool
    {
        return $user->isNot($model) && $this->puede($user, 'eliminar');
    }
}
