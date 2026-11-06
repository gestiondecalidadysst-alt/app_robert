<?php

namespace Tests\Feature\Seguridad;

use App\Actions\Configuracion\CrearEmpresa;
use App\Mail\CorreoCredencialesEmpresa;
use App\Models\Empresa;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Tres frentes: el panel /admin es solo del superadministrador; al crear una empresa su
 * administrador recibe una clave temporal por correo; y esa clave se debe cambiar al entrar.
 */
class AccesoYClaveTempTest extends ConfiguracionTestCase
{
    /** @return array{0: Empresa, 1: string} la empresa creada y la clave temporal del correo */
    private function crearEmpresaDemo(?string $password = null): array
    {
        Mail::fake();

        $empresa = app(CrearEmpresa::class)(
            [
                'razon_social' => 'Transportes Nuevos S.A.S.',
                'nit' => '901000111',
                'municipio_id' => Municipio::first()->id,
                'modulos' => ['vehiculos'],
            ],
            [
                'name' => 'Ana Administradora',
                'email' => 'ana@nuevos.test',
                'documento' => '52000111',
                'password' => $password,
            ],
        );

        $clave = null;
        Mail::assertSent(CorreoCredencialesEmpresa::class, function (CorreoCredencialesEmpresa $correo) use (&$clave) {
            $clave = $correo->claveTemporal;

            return $correo->correo === 'ana@nuevos.test' && $correo->hasTo('ana@nuevos.test');
        });

        return [$empresa, $clave];
    }

    public function test_al_crear_empresa_se_genera_clave_y_se_envia_por_correo(): void
    {
        [, $clave] = $this->crearEmpresaDemo();

        $this->assertNotNull($clave);
        $this->assertGreaterThanOrEqual(12, strlen($clave));

        $admin = User::query()->firstWhere('email', 'ana@nuevos.test');
        $this->assertTrue($admin->debe_cambiar_clave, 'Debe entrar obligado a cambiar la clave');
        $this->assertFalse((bool) $admin->es_superadmin, 'El admin de empresa no es superadministrador');
        $this->assertTrue(Hash::check($clave, $admin->password), 'La clave del correo es la que quedó guardada');
    }

    public function test_el_admin_de_empresa_no_entra_al_panel_admin(): void
    {
        [, $clave] = $this->crearEmpresaDemo();
        $admin = User::query()->firstWhere('email', 'ana@nuevos.test');

        // Ya autenticado como admin de empresa, /admin lo saca a su propio panel.
        $this->actingAs($admin);
        $resp = $this->get('/admin');
        $resp->assertRedirect();
        $this->assertStringContainsString('/app', $resp->headers->get('Location'));
        $this->assertStringNotContainsString('/admin', $resp->headers->get('Location'));

        // El superadministrador sí entra.
        $this->flushSession();
        $this->actingAs($this->usuario('superadmin@ruta.test'));
        $this->get('/admin')->assertOk();
    }

    public function test_el_panel_app_obliga_a_cambiar_la_clave_antes_de_usarlo(): void
    {
        [$empresa] = $this->crearEmpresaDemo();
        $admin = User::query()->firstWhere('email', 'ana@nuevos.test');

        $this->actingAs($admin);

        // Cualquier página del panel redirige a la de cambio de clave.
        $this->get('/app/'.$empresa->slug)->assertRedirect(route('clave.obligatoria'));
    }

    public function test_al_cambiar_la_clave_se_levanta_la_bandera_y_se_puede_seguir(): void
    {
        [, $clave] = $this->crearEmpresaDemo();
        $admin = User::query()->firstWhere('email', 'ana@nuevos.test');
        $this->actingAs($admin);

        $this->post(route('clave.obligatoria.guardar'), [
            'clave' => 'MiClaveNueva2026', 'clave_confirmation' => 'MiClaveNueva2026',
        ])->assertRedirect();

        $admin->refresh();
        $this->assertFalse($admin->debe_cambiar_clave);
        $this->assertTrue(Hash::check('MiClaveNueva2026', $admin->password));
    }

    public function test_la_clave_nueva_no_puede_ser_la_temporal(): void
    {
        [, $clave] = $this->crearEmpresaDemo();
        $admin = User::query()->firstWhere('email', 'ana@nuevos.test');
        $this->actingAs($admin);

        $this->post(route('clave.obligatoria.guardar'), [
            'clave' => $clave, 'clave_confirmation' => $clave,
        ]);

        $admin->refresh();
        $this->assertTrue($admin->debe_cambiar_clave, 'Repetir la temporal no debe levantar la bandera');
    }

    public function test_un_correo_existente_conserva_su_clave_y_no_recibe_correo(): void
    {
        Mail::fake();
        $existente = $this->usuario('admin@demo.test');
        $claveAnterior = $existente->password;

        app(CrearEmpresa::class)(
            ['razon_social' => 'Otra S.A.S.', 'nit' => '901222333', 'municipio_id' => Municipio::first()->id, 'modulos' => ['vehiculos']],
            ['name' => 'Admin Demo', 'email' => 'admin@demo.test'],
        );

        Mail::assertNotSent(CorreoCredencialesEmpresa::class);
        $this->assertSame($claveAnterior, $existente->fresh()->password);
        $this->assertFalse($existente->fresh()->debe_cambiar_clave);
    }
}
