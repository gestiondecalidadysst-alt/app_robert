<?php

namespace App\Actions\Configuracion;

use App\Mail\CorreoCredencialesEmpresa;
use App\Models\Empresa;
use App\Models\Oficina;
use App\Models\PlantillaContrato;
use App\Models\Rol;
use App\Models\User;
use App\Support\Contratos\PlantillaBase;
use App\Support\Mantenimiento\RutinasBase;
use App\Support\Permisos;
use App\Support\Pesv\ItemsBase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
    public function __invoke(array $datosEmpresa, array $administrador, string $nombreOficina = 'Principal', bool $onboarding = true): Empresa
    {
        // En el alta real ($onboarding): si el superadministrador no escribe una clave, se
        // genera una genérica, se envía por correo y el administrador entra obligado a
        // cambiarla. Las semillas y los datos de prueba pasan false: conservan su clave fija.
        $claveTemporal = filled($administrador['password'] ?? null)
            ? (string) $administrador['password']
            : Str::password(12, symbols: false);

        /** @var array{empresa: Empresa, correoCredenciales: ?CorreoCredencialesEmpresa} $resultado */
        $resultado = DB::transaction(function () use ($datosEmpresa, $administrador, $nombreOficina, $claveTemporal, $onboarding): array {
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

            // Un correo ya existente se vincula tal cual: conserva su clave y sus accesos.
            // Solo un administrador nuevo recibe la clave temporal y el correo.
            $usuario = User::query()->firstWhere('email', $administrador['email']);
            $esNuevo = $usuario === null;

            if ($esNuevo) {
                $usuario = User::create([
                    'name' => $administrador['name'],
                    'email' => $administrador['email'],
                    'documento' => $administrador['documento'] ?? null,
                    'password' => $claveTemporal,
                    'debe_cambiar_clave' => $onboarding,
                ]);
            }

            $empresa->usuarios()->syncWithoutDetaching([
                $usuario->id => ['oficina_id' => $oficina->id, 'activo' => true],
            ]);

            (new AsignarRol)($usuario, $empresa, Rol::ADMINISTRADOR);

            self::crearPlantillaContrato($empresa);
            ItemsBase::crear($empresa);
            RutinasBase::crear($empresa);

            $correo = ($esNuevo && $onboarding)
                ? new CorreoCredencialesEmpresa(
                    empresa: $empresa->razon_social,
                    nombre: $usuario->name,
                    correo: $usuario->email,
                    claveTemporal: $claveTemporal,
                    enlace: rtrim((string) config('app.url'), '/').'/app',
                )
                : null;

            return ['empresa' => $empresa, 'correoCredenciales' => $correo];
        });

        // El correo se envía fuera de la transacción: si falla el envío, la empresa ya
        // quedó creada y el superadministrador puede reenviar las credenciales.
        if ($resultado['correoCredenciales'] !== null) {
            Mail::to($administrador['email'])->send($resultado['correoCredenciales']);
        }

        return $resultado['empresa'];
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
