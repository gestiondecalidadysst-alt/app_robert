<?php

namespace Tests\Feature\Configuracion;

use App\Actions\Configuracion\AsignarRol;
use App\Filament\Admin\Resources\Empresas\Pages\CrearEmpresa;
use App\Filament\App\Resources\Roles\Pages\CrearRol;
use App\Filament\App\Resources\Roles\RolResource;
use App\Filament\App\Resources\Usuarios\Pages\CrearUsuario;
use App\Filament\App\Resources\Usuarios\Pages\EditarUsuario;
use App\Models\Empresa;
use App\Models\Oficina;
use App\Models\Rol;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

class GestionUsuariosRolesTest extends ConfiguracionTestCase
{
    public function test_crea_un_usuario_con_rol_y_oficina(): void
    {
        $this->entrarComo('admin@demo.test');
        $rol = Rol::where('name', Rol::AUXILIAR)->first();
        $oficina = Oficina::first();

        Livewire::test(CrearUsuario::class)
            ->fillForm([
                'name' => 'Nuevo Auxiliar',
                'email' => 'nuevo@demo.test',
                'rol_id' => $rol->id,
                'oficina_id' => $oficina->id,
                'acceso_activo' => true,
                'password' => 'Secreta123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $usuario = User::where('email', 'nuevo@demo.test')->firstOrFail();
        $this->assertSame(Rol::AUXILIAR, AsignarRol::rolActual($usuario, $this->demo)->name);
        $this->assertSame($oficina->id, $usuario->empresas()->first()->pivot->oficina_id);
    }

    public function test_un_administrador_de_empresa_no_puede_vincular_a_un_usuario_de_otra(): void
    {
        // Vincular por correo daba acceso a la cuenta ajena: una vez dentro del listado,
        // «Editar» permitía cambiarle la contraseña, que es global. Ver AislamientoUsuariosTest.
        $this->entrarComo('admin@demo.test');

        Livewire::test(CrearUsuario::class)
            ->fillForm([
                'name' => 'Otro nombre',
                'email' => 'admin@ejemplo.test',
                'rol_id' => Rol::where('name', Rol::GERENCIA)->first()->id,
                'acceso_activo' => true,
                'password' => 'OtraClave123',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertFalse($this->usuario('admin@ejemplo.test')->empresas()->whereKey($this->demo->id)->exists());
    }

    public function test_el_superadministrador_si_vincula_un_correo_existente_sin_duplicar_ni_cambiar_su_clave(): void
    {
        // La persona que trabaja en dos empresas del grupo la vincula quien administra
        // la plataforma, no el administrador de una de las empresas.
        $this->entrarComo('superadmin@ruta.test');
        $ajeno = $this->usuario('admin@ejemplo.test');
        $claveAnterior = $ajeno->password;

        Livewire::test(CrearUsuario::class)
            ->fillForm([
                'name' => 'Otro nombre',
                'email' => 'admin@ejemplo.test',
                'rol_id' => Rol::where('name', Rol::GERENCIA)->first()->id,
                'acceso_activo' => true,
                'password' => 'OtraClave123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ajeno->refresh();
        $this->assertSame(1, User::where('email', 'admin@ejemplo.test')->count());
        $this->assertSame($claveAnterior, $ajeno->password);
        $this->assertSame(Rol::GERENCIA, AsignarRol::rolActual($ajeno, $this->demo)->name);
        // Conserva su rol de administrador en su propia empresa.
        $this->assertSame(Rol::ADMINISTRADOR, AsignarRol::rolActual($ajeno, $this->otra)->name);
    }

    public function test_cambiar_rol_en_una_empresa_no_afecta_la_otra(): void
    {
        $this->entrarComo('admin@demo.test');
        $ajeno = $this->usuario('admin@ejemplo.test');
        $this->demo->usuarios()->attach($ajeno->id, ['activo' => true]);
        (new AsignarRol)($ajeno, $this->demo, Rol::AUXILIAR);

        Livewire::test(EditarUsuario::class, ['record' => $ajeno->getKey()])
            ->fillForm(['rol_id' => Rol::where('name', Rol::GERENCIA)->first()->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Rol::GERENCIA, AsignarRol::rolActual($ajeno, $this->demo)->name);
        $this->assertSame(Rol::ADMINISTRADOR, AsignarRol::rolActual($ajeno, $this->otra)->name);
    }

    public function test_crea_un_rol_con_permisos_desde_la_matriz(): void
    {
        $this->entrarComo('admin@demo.test');

        Livewire::test(CrearRol::class)
            ->fillForm([
                'name' => 'Despachador',
                RolResource::campoPermisos('Configuración · Oficinas') => ['ver_oficinas', 'crear_oficinas'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rol = Rol::where('name', 'Despachador')->firstOrFail();
        $this->assertSame($this->demo->id, $rol->empresa_id);
        $this->assertEqualsCanonicalizing(['ver_oficinas', 'crear_oficinas'], $rol->permissions->pluck('name')->all());
    }

    public function test_el_superadmin_crea_una_empresa_con_su_administrador(): void
    {
        $this->actingAs($this->usuario('superadmin@ruta.test'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CrearEmpresa::class)
            ->fillForm([
                'nit' => '800197268',
                'razon_social' => 'Expreso Nuevo S.A.S.',
                'administrador' => [
                    'name' => 'Admin Nuevo',
                    'email' => 'admin@nuevo.test',
                    'password' => 'Secreta123',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $empresa = Empresa::where('razon_social', 'Expreso Nuevo S.A.S.')->firstOrFail();
        $this->assertSame(4, $empresa->dv);
        $this->assertSame('expreso-nuevo-sas', $empresa->slug);
        $this->assertSame(5, Rol::query()->deEmpresa($empresa)->count());
        $this->assertSame(1, $empresa->oficinas()->count());

        $admin = User::where('email', 'admin@nuevo.test')->firstOrFail();
        $this->assertSame(Rol::ADMINISTRADOR, AsignarRol::rolActual($admin, $empresa)->name);
    }
}
