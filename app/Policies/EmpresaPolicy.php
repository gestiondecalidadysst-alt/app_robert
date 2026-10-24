<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;
use Illuminate\Database\Eloquent\Model;

class EmpresaPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'empresa';

    /** Las empresas solo se crean desde el panel del superadministrador. */
    public function create(User $user): bool
    {
        return false;
    }

    /** Las empresas no se borran: se desactivan. */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
