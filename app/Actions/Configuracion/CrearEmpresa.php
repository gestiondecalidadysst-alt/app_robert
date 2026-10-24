<?php

namespace App\Actions\Configuracion;

use App\Models\Empresa;
use App\Models\Oficina;
use App\Models\Rol;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Da de alta una empresa nueva en la plataforma:
 * la empresa, sus roles por defecto, una oficina principal y su primer administrador.
 */
class CrearEmpresa
{
    /**
     * @param  array  $datosEmpresa  Columnas de la tabla empresas.
     * @param  array{name: string, email: string, password?: string|null, documento?: string|null}  $administrador
     */
    public function __invoke(array $datosEmpresa, array $administrador, string $nombreOficina = 'Principal'): Empresa
    {
        return DB::transaction(function () use ($datosEmpresa, $administrador, $nombreOficina): Empresa {
            app(SincronizarPermisos::class)();

            $empresa = Empresa::create($datosEmpresa);

            $this->crearRoles($empresa);

            $oficina = Oficina::create([
                'empresa_id' => $empresa->id,
                'nombre' => $nombreOficina,
                'municipio_id' => $empresa->municipio_id,
                'direccion' => $empresa->direccion,
                'telefono' => $empresa->telefono,
            ]);

            $usuario = User::query()->firstWhere('email', $administrador['email']);
            if (! $usuario) {
                $usuario = User::create([
                    'name' => $administrador['name'],
                    'email' => $administrador['email'],
                    'documento' => $administrador['documento'] ?? null,
                    'password' => $administrador['password'],
                ]);
            }

            $empresa->usuarios()->syncWithoutDetaching([
                $usuario->id => ['oficina_id' => $oficina->id, 'activo' => true],
            ]);

            (new AsignarRol)($usuario, $empresa, Rol::ADMINISTRADOR);

            return $empresa;
        });
    }

    /** Crea (o completa) los roles por defecto de una empresa con sus permisos. */
    public function crearRoles(Empresa $empresa): void
    {
        $registrar = app(PermissionRegistrar::class);
        $equipoAnterior = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($empresa->id);

        foreach (Permisos::porRol() as $nombre => $permisos) {
            $rol = Rol::query()->deEmpresa($empresa)->firstOrCreate(
                ['name' => $nombre, 'guard_name' => 'web', 'empresa_id' => $empresa->id],
            );
            $rol->syncPermissions($permisos);
        }

        $registrar->setPermissionsTeamId($equipoAnterior);
        $registrar->forgetCachedPermissions();
    }
}
