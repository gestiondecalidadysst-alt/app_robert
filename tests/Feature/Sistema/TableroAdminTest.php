<?php

namespace Tests\Feature\Sistema;

use App\Filament\Admin\Resources\Empresas\EmpresaResource;
use App\Filament\Admin\Widgets\AltasEmpresasChart;
use App\Filament\Admin\Widgets\EstadoPlataforma;
use App\Filament\Admin\Widgets\ResumenEmpresa;
use App\Filament\Admin\Widgets\ResumenPlataforma;
use App\Filament\Admin\Widgets\UltimasEmpresas;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Tablero de inicio del panel del superadministrador: los widgets de resumen cargan y
 * muestran el pulso de la plataforma.
 */
class TableroAdminTest extends ConfiguracionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->usuario('superadmin@ruta.test'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_el_resumen_de_la_plataforma_carga(): void
    {
        Livewire::test(ResumenPlataforma::class)
            ->assertOk()
            ->assertSee('Empresas')
            ->assertSee('Usuarios de la plataforma')
            ->assertSee('FUEC expedidos (mes)');
    }

    public function test_el_estado_del_sistema_carga(): void
    {
        Livewire::test(EstadoPlataforma::class)
            ->assertOk()
            ->assertSee('Trabajos fallidos')
            ->assertSee('Webhooks pendientes');
    }

    public function test_las_ultimas_empresas_cargan_y_muestran_las_sembradas(): void
    {
        Livewire::test(UltimasEmpresas::class)
            ->assertOk()
            ->assertSee($this->demo->razon_social);
    }

    public function test_el_grafico_de_altas_carga(): void
    {
        Livewire::test(AltasEmpresasChart::class)->assertOk();
    }

    public function test_la_pantalla_de_inicio_del_admin_abre(): void
    {
        $this->get('/admin')
            ->assertOk()
            // El pie del menú lateral se oculta al colapsar (ligado al estado del sidebar).
            ->assertSee('x-show="$store.sidebar.isOpen || $store.sidebar.isOpenMobile"', false);
    }

    public function test_el_resumen_de_una_empresa_muestra_su_estado(): void
    {
        Livewire::test(ResumenEmpresa::class, ['record' => $this->demo])
            ->assertOk()
            ->assertSee('Estado')
            ->assertSee('Usuarios')
            ->assertSee('Consumo del mes');
    }

    public function test_la_ficha_de_empresa_abre_con_el_resumen(): void
    {
        $this->get(EmpresaResource::getUrl('edit', ['record' => $this->demo]))->assertOk();
    }
}
