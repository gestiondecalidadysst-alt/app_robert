<?php

namespace App\Actions\Configuracion;

use App\Models\Empresa;
use App\Models\Oficina;
use App\Models\PlantillaContrato;
use App\Support\Contratos\PlantillaBase;
use App\Models\Rol;
use App\Models\User;
use App\Support\Permisos;
use App\Support\Pesv\ItemsBase;
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

            self::crearPlantillaContrato($empresa);
            ItemsBase::crear($empresa);
            \App\Support\Mantenimiento\RutinasBase::crear($empresa);

            return $empresa;
        });
    }

    /** Versión 1 de la plantilla de contrato con el texto base, si la empresa no tiene. */
    public static function crearPlantillaContrato(Empresa $empresa): void
    {
        if (PlantillaContrato::query()->deEmpresa($empresa)->exists()) {
            return;
        }

        $plantilla = new PlantillaContrato([
            'version' => 1,
            'titulo' => PlantillaBase::TITULO,
            'contenido' => PlantillaBase::contenido(),
            'notas_cambio' => 'Texto base del sistema.',
        ]);
        $plantilla->empresa_id = $empresa->id;
        $plantilla->save();
    }

    /**
     * Crea (o completa) los roles por defecto de una empresa.
     *
     * - Un rol que se crea ahora recibe todos sus permisos por defecto.
     * - Un rol que ya existía conserva los ajustes que haya hecho la empresa: solo se le
     *   agregan los permisos por defecto que estén en $nuevos (los de un módulo recién agregado).
     *
     * @param  list<string>  $nuevos
     */
    public function crearRoles(Empresa $empresa, array $nuevos = []): void
    {
        $registrar = app(PermissionRegistrar::class);
        $equipoAnterior = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($empresa->id);

        foreach (Permisos::porRol() as $nombre => $permisos) {
            $rol = Rol::query()->deEmpresa($empresa)->firstOrCreate(
                ['name' => $nombre, 'guard_name' => 'web', 'empresa_id' => $empresa->id],
            );

            if ($rol->wasRecentlyCreated) {
                $rol->syncPermissions($permisos);
            } elseif ($agregar = array_values(array_intersect($permisos, $nuevos))) {
                $rol->givePermissionTo($agregar);
            }
        }

        $registrar->setPermissionsTeamId($equipoAnterior);
        $registrar->forgetCachedPermissions();
    }
}
