<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;
use Illuminate\Database\Eloquent\Model;

class ActividadPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'auditoria';

    public function create(User $user): bool
    {
        return false;
    }

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
