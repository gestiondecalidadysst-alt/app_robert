<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Política genérica: traduce las habilidades de Laravel/Filament a permisos
 * "<accion>_<recurso>" de la empresa activa. Cada política define RECURSO.
 *
 * El superadministrador pasa siempre (ver Gate::before en AppServiceProvider).
 */
trait AutorizaPorPermiso
{
    protected function puede(User $user, string $accion): bool
    {
        return $user->can($accion . '_' . static::RECURSO);
    }

    public function viewAny(User $user): bool
    {
        return $this->puede($user, 'ver');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->puede($user, 'ver');
    }

    public function create(User $user): bool
    {
        return $this->puede($user, 'crear');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->puede($user, 'editar');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->puede($user, 'eliminar');
    }

    public function deleteAny(User $user): bool
    {
        return $this->puede($user, 'eliminar');
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->puede($user, 'eliminar');
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Model $model): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
