<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\Mantenimiento;
use App\Models\PlanAccion;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Pesv\ServiciosConductor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga (o muestra) una evidencia del disco privado. Pueden verla:
 * - usuarios de la empresa con permiso de ver mantenimientos o planes de acción;
 * - el conductor que tiene acceso al vehículo (asignado o con FUEC vigente).
 */
class EvidenciaController extends Controller
{
    public function __invoke(Request $request, int $evidencia): StreamedResponse
    {
        $evidencia = Evidencia::query()->withoutGlobalScopes()->findOrFail($evidencia);
        $adjuntable = $evidencia->adjuntable()->withoutGlobalScopes()->first();
        abort_unless($adjuntable instanceof Mantenimiento || $adjuntable instanceof PlanAccion, 404);
        abort_unless($this->puedeVer($request->user(), $evidencia, $adjuntable), 404);
        abort_unless($evidencia->existe(), 404, 'El archivo ya no está en el servidor.');

        $disposicion = $evidencia->esImagen() || $evidencia->mime === 'application/pdf' ? 'inline' : 'attachment';

        return Storage::disk(Evidencia::DISCO)->response($evidencia->path, $evidencia->nombre, [
            'Content-Type' => $evidencia->mime ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposicion);
    }

    private function puedeVer(?User $usuario, Evidencia $evidencia, Mantenimiento|PlanAccion $adjuntable): bool
    {
        if (! $usuario) {
            return false;
        }
        if ($usuario->es_superadmin) {
            return true;
        }

        $empresa = $usuario->empresasDisponibles()->firstWhere('id', $evidencia->empresa_id);
        if ($empresa) {
            $registrar = app(PermissionRegistrar::class);
            $anterior = $registrar->getPermissionsTeamId();
            $registrar->setPermissionsTeamId($empresa->id);
            $usuario->unsetRelation('roles')->unsetRelation('permissions');
            $puede = $usuario->can($adjuntable instanceof Mantenimiento ? 'ver_mantenimientos' : 'ver_planes_accion');
            $registrar->setPermissionsTeamId($anterior);

            if ($puede) {
                return true;
            }
        }

        $vehiculo = Vehiculo::query()->withoutGlobalScopes()->find($adjuntable->vehiculo_id);

        return $vehiculo && ServiciosConductor::puedeInspeccionar($usuario, $vehiculo);
    }
}
