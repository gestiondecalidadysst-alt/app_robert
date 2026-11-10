<?php

namespace Tests\Feature\Configuracion;

use App\Filament\Admin\Resources\Empresas\Pages\EditarEmpresa as EditarEmpresaAdmin;
use App\Filament\App\Pages\Tenancy\EditarEmpresa as MiEmpresa;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * La firma escaneada del representante legal se gestiona en el panel de la empresa (por temas
 * legales), no en el del superadministrador.
 */
class FirmaRepresentanteTest extends ConfiguracionTestCase
{
    public function test_la_firma_es_visible_en_el_panel_de_la_empresa(): void
    {
        $this->entrarComo('admin@demo.test', $this->demo);

        Livewire::test(MiEmpresa::class)
            ->assertOk()
            ->assertFormFieldIsVisible('firma_representante_path');
    }

    public function test_la_firma_esta_oculta_en_el_panel_del_superadministrador(): void
    {
        $this->actingAs($this->usuario('superadmin@ruta.test'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditarEmpresaAdmin::class, ['record' => $this->demo->getKey()])
            ->assertOk()
            ->assertFormFieldIsHidden('firma_representante_path');
    }
}
