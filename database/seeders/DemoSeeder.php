<?php

namespace Database\Seeders;

use App\Actions\Configuracion\AsignarRol;
use App\Actions\Configuracion\CrearEmpresa;
use App\Models\ClaseVehiculo;
use App\Models\Empresa;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Rol;
use App\Models\TipoIdentificacion;
use App\Models\User;
use App\Models\ValorAdministracion;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración (solo desarrollo y pruebas). Contraseña de todos: Ruta2026*
 */
class DemoSeeder extends Seeder
{
    public const CLAVE = 'Ruta2026*';

    public function run(): void
    {
        User::updateOrCreate(['email' => 'superadmin@ruta.test'], [
            'name' => 'Superadministrador',
            'password' => self::CLAVE,
            'es_superadmin' => true,
        ]);

        $nit = TipoIdentificacion::firstWhere('codigo', 'NIT')?->id;
        $monteria = Municipio::firstWhere('codigo_dane', '23001')?->id;

        $demo = Empresa::firstWhere('slug', 'cootrasec-demo') ?? app(CrearEmpresa::class)([
            'slug' => 'cootrasec-demo',
            'tipo_identificacion_id' => $nit,
            'nit' => '900123456',
            'razon_social' => 'Cooperativa de Transportadores Demo',
            'nombre_comercial' => 'COOTRASEC (demo)',
            'municipio_id' => $monteria,
            'direccion' => 'Calle 1 # 1-1',
            'telefono' => '6040000000',
            'email' => 'contacto@demo.test',
        ], [
            'name' => 'Administrador Demo',
            'email' => 'admin@demo.test',
            'password' => self::CLAVE,
        ], 'Montería', onboarding: false);

        Oficina::query()->deEmpresa($demo)->firstOrCreate(
            ['empresa_id' => $demo->id, 'nombre' => 'Montería Oppal'],
            ['municipio_id' => $monteria],
        );

        foreach ([
            ['gerencia@demo.test', 'Gerente Demo', Rol::GERENCIA],
            ['auxiliar@demo.test', 'Auxiliar Demo', Rol::AUXILIAR],
        ] as [$email, $nombre, $rol]) {
            $usuario = User::updateOrCreate(['email' => $email], ['name' => $nombre, 'password' => self::CLAVE]);
            $demo->usuarios()->syncWithoutDetaching([$usuario->id => ['activo' => true]]);
            (new AsignarRol)($usuario, $demo, $rol);
        }

        foreach (['Bus' => 450000, 'Buseta' => 380000, 'Microbús' => 320000, 'Camioneta' => 250000, 'Automóvil' => 200000] as $clase => $valor) {
            $claseId = ClaseVehiculo::firstWhere('nombre', $clase)?->id;
            if ($claseId) {
                ValorAdministracion::query()->deEmpresa($demo)->firstOrCreate(
                    ['empresa_id' => $demo->id, 'clase_vehiculo_id' => $claseId, 'vigente_desde' => '2026-01-01'],
                    ['valor' => $valor],
                );
            }
        }

        // Segunda empresa, para comprobar que los datos no se mezclan.
        if (! Empresa::where('slug', 'transportes-ejemplo')->exists()) {
            app(CrearEmpresa::class)([
                'slug' => 'transportes-ejemplo',
                'tipo_identificacion_id' => $nit,
                'nit' => '901987654',
                'razon_social' => 'Transportes Ejemplo S.A.S.',
                'municipio_id' => $monteria,
            ], [
                'name' => 'Administrador Ejemplo',
                'email' => 'admin@ejemplo.test',
                'password' => self::CLAVE,
            ], onboarding: false);
        }

        $this->call([DemoFlotaSeeder::class, DemoConductoresSeeder::class, DemoOperacionSeeder::class, DemoPesvSeeder::class]);
    }
}
