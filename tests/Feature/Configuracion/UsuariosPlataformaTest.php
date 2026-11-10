<?php

namespace Tests\Feature\Configuracion;

use App\Filament\Admin\Resources\Usuarios\Pages\GestionarUsuarios;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * La lista de usuarios del panel de plataforma muestra por defecto solo las cuentas
 * administrativas (administradores de empresa y superadministradores); el resto aparece
 * al quitar el filtro.
 */
class UsuariosPlataformaTest extends ConfiguracionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->usuario('superadmin@ruta.test'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_por_defecto_solo_administradores_y_superadmins(): void
    {
        $admin = $this->usuario('admin@demo.test');       // Administrador de empresa
        $super = $this->usuario('superadmin@ruta.test');  // Superadministrador
        $gerente = $this->usuario('gerencia@demo.test');  // Rol Gerencia: no es administrador

        Livewire::test(GestionarUsuarios::class)
            ->assertCanSeeTableRecords([$admin, $super])
            ->assertCanNotSeeTableRecords([$gerente]);
    }

    public function test_al_quitar_el_filtro_aparecen_todos_los_usuarios(): void
    {
        $gerente = $this->usuario('gerencia@demo.test');

        Livewire::test(GestionarUsuarios::class)
            ->removeTableFilter('solo_administradores')
            ->assertCanSeeTableRecords([$gerente]);
    }
}
