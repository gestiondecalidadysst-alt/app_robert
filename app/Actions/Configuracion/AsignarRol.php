<?php

namespace App\Actions\Configuracion;

use App\Models\Empresa;
use App\Models\Rol;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deja al usuario con un único rol dentro de la empresa indicada.
 * Los roles de otras empresas no se tocan.
 */
class AsignarRol
{
    public function __invoke(User $usuario, Empresa $empresa, Rol|int|string|null $rol): void
    {
        $registrar = app(PermissionRegistrar::class);
        $equipoAnterior = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($empresa->id);

        $usuario->unsetRelation('roles')->unsetRelation('permissions');

        if ($rol === null) {
            $usuario->syncRoles([]);
        } else {
            $modelo = $rol instanceof Rol
                ? $rol
                : Rol::query()->deEmpresa($empresa)
                    ->where(is_int($rol) ? 'id' : 'name', $rol)
                    ->firstOrFail();

            $usuario->syncRoles([$modelo]);
        }

        $usuario->unsetRelation('roles')->unsetRelation('permissions');
        $registrar->setPermissionsTeamId($equipoAnterior);
    }

    /** Rol actual del usuario dentro de una empresa. */
    public static function rolActual(User $usuario, Empresa $empresa): ?Rol
    {
        return Rol::query()
            ->deEmpresa($empresa)
            ->whereHas('users', fn ($q) => $q->whereKey($usuario->getKey())
                ->where(config('permission.table_names.model_has_roles').'.empresa_id', $empresa->id))
            ->first();
    }
}
