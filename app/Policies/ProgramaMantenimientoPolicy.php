<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AutorizaPorPermiso;
use Illuminate\Database\Eloquent\Model;

/** "Editar" = reprogramar, cancelar e incorporar vehículos; "Aprobar" = aprobar el programa. No se borra. */
class ProgramaMantenimientoPolicy
{
    use AutorizaPorPermiso;

    public const RECURSO = 'programas_mantenimiento';

    public function aprobar(User $user, Model $model): bool
    {
        return $this->puede($user, 'aprobar');
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
