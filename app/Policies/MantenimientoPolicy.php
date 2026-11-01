<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;
use Illuminate\Database\Eloquent\Model;

/** Un mantenimiento no se edita ni se borra: se anula. */
class MantenimientoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'mantenimientos';

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
