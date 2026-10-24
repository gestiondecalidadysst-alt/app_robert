<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se ejecuta en cada petición del panel de empresa, ya identificada la empresa:
 * - Fija la empresa como "team" de spatie/permission (roles y permisos por empresa).
 * - Recuerda la última empresa usada y la hora del último acceso.
 */
class PrepararEmpresaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $empresa = Filament::getTenant();
        $usuario = $request->user();

        if ($empresa instanceof Empresa) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->id);

            if ($usuario) {
                $usuario->unsetRelation('roles')->unsetRelation('permissions');

                if ($usuario->ultima_empresa_id !== $empresa->id || $usuario->ultimo_acceso_at?->lt(now()->subMinutes(5))) {
                    $usuario->forceFill([
                        'ultima_empresa_id' => $empresa->id,
                        'ultimo_acceso_at' => now(),
                    ])->saveQuietly();
                }
            }
        }

        return $next($request);
    }
}
