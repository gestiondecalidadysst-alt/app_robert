<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;
use Illuminate\Database\Eloquent\Model;

/** Las inspecciones no se editan ni se borran: son evidencia. */
class InspeccionPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'inspecciones';

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
