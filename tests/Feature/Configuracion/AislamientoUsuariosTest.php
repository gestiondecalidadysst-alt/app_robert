<?php

namespace Tests\Feature\Configuracion;

use App\Filament\App\Resources\Usuarios\Pages\CrearUsuario;
use App\Filament\App\Resources\Usuarios\Pages\EditarUsuario;
use App\Models\Empresa;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * Aislamiento entre empresas al administrar usuarios.
 *
 * El alta buscaba el correo en TODA la plataforma y enganchaba el usuario encontrado
 * a la empresa de quien lo creaba, sin su consentimiento; después, «Editar» permitía
 * cambiarle la contraseña. Encadenado, eso daba a un administrador de la empresa A la
 * cuenta de cualquier usuario de la empresa B, superadministradores incluidos.
 */
class AislamientoUsuariosTest extends ConfiguracionTestCase
{
    private function otraEmpresa(): Empresa
    {
        return Empresa::query()->withoutGlobalScopes()->create([
            'razon_social' => 'Transportes Ajenos S.A.S.',
            'slug' => 'ajenos',
            'nit' => '900999888',
            'activa' => true,
        ]);
    }

    private function victimaEn(Empresa $empresa, string $email, bool $superadmin = false): User
    {
        $victima = User::query()->withoutGlobalScopes()->create([
            'name' => 'Víctima Ajena',
            'email' => $email,
            'password' => Hash::make('ClaveOriginal2026*'),
            'activo' => true,
            'es_superadmin' => $superadmin,
        ]);

        $empresa->usuarios()->syncWithoutDetaching([$victima->getKey() => ['activo' => true]]);

        return $victima;
    }

    private function rolAdministrador(): int
    {
        return Rol::query()->deEmpresa($this->demo)->where('nombre', 'Administrador')->value('id')
            ?? Rol::query()->deEmpresa($this->demo)->value('id');
    }

    public function test_no_se_puede_enganchar_a_la_empresa_un_usuario_de_otra(): void
    {
        $ajena = $this->otraEmpresa();
        $victima = $this->victimaEn($ajena, 'victima@ajenos.test');

        $this->entrarComo('admin@demo.test');

        Livewire::test(CrearUsuario::class)
            ->fillForm([
                'name' => 'Víctima Ajena',
                'email' => 'victima@ajenos.test',
                'password' => 'LaQueYoQuiera2026*',
                'rol_id' => $this->rolAdministrador(),
                'acceso_activo' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);

        // Ni queda vinculada a la empresa del atacante, ni le cambian la contraseña.
        $this->assertFalse($victima->fresh()->empresas()->whereKey($this->demo->id)->exists());
        $this->assertTrue(Hash::check('ClaveOriginal2026*', $victima->fresh()->password));
    }

    public function test_no_se_puede_enganchar_a_un_superadministrador(): void
    {
        $ajena = $this->otraEmpresa();
        $victima = $this->victimaEn($ajena, 'super@plataforma.test', superadmin: true);

        $this->entrarComo('admin@demo.test');

        Livewire::test(CrearUsuario::class)
            ->fillForm([
                'name' => 'Super',
                'email' => 'super@plataforma.test',
                'password' => 'LaQueYoQuiera2026*',
                'rol_id' => $this->rolAdministrador(),
                'acceso_activo' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertFalse($victima->fresh()->empresas()->whereKey($this->demo->id)->exists());
    }

    public function test_no_se_le_cambia_la_contrasena_a_un_usuario_compartido_con_otra_empresa(): void
    {
        // Escenario ya existente: una persona que trabaja en dos empresas del grupo.
        $ajena = $this->otraEmpresa();
        $compartido = $this->victimaEn($ajena, 'compartido@grupo.test');
        $this->demo->usuarios()->syncWithoutDetaching([$compartido->getKey() => ['activo' => true]]);

        $this->entrarComo('admin@demo.test');

        Livewire::test(EditarUsuario::class, ['record' => $compartido->getRouteKey()])
            ->fillForm(['name' => 'Compartido', 'email' => 'compartido@grupo.test',
                'password' => 'LaQueYoQuiera2026*', 'rol_id' => $this->rolAdministrador(), 'acceso_activo' => true])
            ->call('save');

        // El nombre sí puede cambiarlo; la contraseña no, porque abriría la otra empresa.
        $this->assertTrue(Hash::check('ClaveOriginal2026*', $compartido->fresh()->password));
    }

    public function test_un_administrador_de_empresa_no_edita_a_un_superadministrador(): void
    {
        $superadmin = $this->victimaEn($this->demo, 'super2@plataforma.test', superadmin: true);

        $this->entrarComo('admin@demo.test');

        $this->assertFalse($this->usuario('admin@demo.test')->can('update', $superadmin));
    }

    public function test_el_alta_normal_de_un_usuario_nuevo_sigue_funcionando(): void
    {
        $this->entrarComo('admin@demo.test');

        Livewire::test(CrearUsuario::class)
            ->fillForm([
                'name' => 'Auxiliar Nuevo',
                'email' => 'nuevo@demo.test',
                'password' => 'ClaveNueva2026*',
                'rol_id' => $this->rolAdministrador(),
                'acceso_activo' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = User::query()->withoutGlobalScopes()->firstWhere('email', 'nuevo@demo.test');

        $this->assertNotNull($nuevo);
        $this->assertTrue($nuevo->empresas()->whereKey($this->demo->id)->exists());
    }
}
