<?php

namespace Tests\Feature\Sistema;

use App\Filament\Admin\Pages\Manual;
use App\Filament\Admin\Resources\Manual\Pages\CrearSeccionManual;
use App\Models\SeccionManual;
use Database\Seeders\ManualSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Manual de la aplicación en el panel de plataforma: lectura, descarga en PDF (solo superadmin)
 * y edición de secciones.
 */
class ManualTest extends ConfiguracionTestCase
{
    private function entrarComoSuperadmin(): void
    {
        $this->actingAs($this->usuario('superadmin@ruta.test'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_el_manual_muestra_las_secciones_sembradas(): void
    {
        $this->seed(ManualSeeder::class);
        $this->entrarComoSuperadmin();

        Livewire::test(Manual::class)
            ->assertOk()
            ->assertSee('Visión general de la plataforma')
            ->assertSee('Operación: clientes, contratos y FUEC');
    }

    public function test_el_superadmin_descarga_el_manual_en_pdf(): void
    {
        $this->actingAs($this->usuario('superadmin@ruta.test'));

        $r = $this->get(route('admin.manual.pdf'));
        $r->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('content-type'));
    }

    public function test_un_usuario_que_no_es_superadmin_no_descarga_el_manual(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));

        $this->get(route('admin.manual.pdf'))->assertForbidden();
    }

    public function test_se_puede_crear_una_seccion_del_manual(): void
    {
        $this->entrarComoSuperadmin();

        Livewire::test(CrearSeccionManual::class)
            ->fillForm([
                'grupo' => 'Flota',
                'titulo' => 'Vehículos y hoja de vida',
                'orden' => 1,
                'contenido' => '<p>Contenido de prueba.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(SeccionManual::query()->where('titulo', 'Vehículos y hoja de vida')->exists());
    }
}
