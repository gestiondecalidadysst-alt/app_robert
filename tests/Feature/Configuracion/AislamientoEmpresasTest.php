<?php

namespace Tests\Feature\Configuracion;

use App\Models\Oficina;
use App\Models\ValorAdministracion;

class AislamientoEmpresasTest extends ConfiguracionTestCase
{
    public function test_las_consultas_solo_traen_datos_de_la_empresa_activa(): void
    {
        Oficina::create(['empresa_id' => $this->otra->id, 'nombre' => 'Sede ajena']);

        $this->entrarComo('admin@demo.test');

        $this->assertSame([$this->demo->id], Oficina::query()->distinct()->pluck('empresa_id')->all());
        $this->assertFalse(Oficina::query()->where('nombre', 'Sede ajena')->exists());
        $this->assertTrue(ValorAdministracion::query()->get()->every(fn ($v) => $v->empresa_id === $this->demo->id));
    }

    public function test_al_crear_se_asigna_la_empresa_activa(): void
    {
        $this->entrarComo('admin@demo.test');

        $oficina = Oficina::create(['nombre' => 'Nueva sede']);

        $this->assertSame($this->demo->id, $oficina->empresa_id);
    }

    public function test_los_cambios_quedan_en_auditoria_de_la_empresa(): void
    {
        $this->entrarComo('admin@demo.test');

        $oficina = Oficina::create(['nombre' => 'Sede auditada']);
        $oficina->update(['telefono' => '3000000000']);

        $actividad = \App\Models\Actividad::query()->latest('id')->first();
        $this->assertSame($this->demo->id, $actividad->empresa_id);
        $this->assertSame('updated', $actividad->event);
    }
}
