<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\User;
use Filament\Notifications\Notification;
use Spatie\Permission\PermissionRegistrar;

/**
 * Avisos en la campana del panel a los usuarios activos de una empresa que tienen un permiso.
 */
final class Avisos
{
    public static function aQuienPueda(Empresa $empresa, string $permiso, Notification $notificacion): void
    {
        $registrar = app(PermissionRegistrar::class);
        $anterior = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($empresa->id);

        try {
            $empresa->usuarios()->wherePivot('activo', true)->where('users.activo', true)->get()
                ->filter(fn (User $u) => $u->unsetRelation('roles')->unsetRelation('permissions')->can($permiso))
                ->each(fn (User $u) => $notificacion->sendToDatabase($u));
        } finally {
            $registrar->setPermissionsTeamId($anterior);
        }
    }
}
