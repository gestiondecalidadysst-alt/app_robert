<?php

namespace Tests\Feature\Sistema;

use App\Filament\Admin\Resources\Auditoria\Pages\ListarAuditoria;
use App\Models\Cliente;
use App\Models\TipoIdentificacion;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Auditoría global del panel de superadministrador: ve la actividad de todas las empresas,
 * mientras cada empresa conserva la suya en su propio panel.
 */
class AuditoriaGlobalTest extends ConfiguracionTestCase
{
    public function test_la_auditoria_global_ve_la_actividad_de_todas_las_empresas(): void
    {
        $tipo = TipoIdentificacion::query()->firstOrFail()->id;

        // Genera actividad auditable en dos empresas distintas.
        $this->entrarComo('admin@demo.test', $this->demo);
        Cliente::create(['tipo_identificacion_id' => $tipo, 'documento' => '901000111', 'nombre' => 'Cliente Demo Audit']);

        $this->entrarComo('admin@ejemplo.test', $this->otra);
        Cliente::create(['tipo_identificacion_id' => $tipo, 'documento' => '901000222', 'nombre' => 'Cliente Otra Audit']);

        // Como superadministrador, la auditoría global ve ambas empresas.
        $this->actingAs($this->usuario('superadmin@ruta.test'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListarAuditoria::class)
            ->assertOk()
            ->assertSee($this->demo->razon_social)
            ->assertSee($this->otra->razon_social);
    }
}
