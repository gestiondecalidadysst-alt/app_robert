<?php

namespace Tests\Feature\Carga;

use App\Enums\ModuloEmpresa;
use App\Models\Conductor;
use App\Models\CumplidoManifiesto;
use App\Models\CumplidoRemesa;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\RemesaCarga;
use App\Models\TerceroCarga;
use App\Models\TipoIdentificacion;
use App\Models\Vehiculo;
use App\Services\Rndc\Rndc;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class CumplidosTest extends ConfiguracionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->demo->update(['modulos' => [...ModuloEmpresa::porDefecto(), ModuloEmpresa::Carga->value]]);
    }

    private function manifiestoRegistrado(): ManifiestoCarga
    {
        static $i = 0;
        $i++;

        $remi = new TerceroCarga(['tipo_identificacion_id' => TipoIdentificacion::where('codigo', 'CC')->value('id'),
            'documento' => '100000' . $i, 'nombre' => 'A', 'primer_apellido' => 'B', 'celular' => '3001234567',
            'direccion' => 'x', 'municipio_id' => Municipio::where('codigo_dane', '76001')->value('id')]);
        $remi->empresa_id = $this->demo->id; $remi->save();
        app(Rndc::class)->marcarManual($remi, '100' . $i);

        $dest = new TerceroCarga(['tipo_identificacion_id' => TipoIdentificacion::where('codigo', 'CC')->value('id'),
            'documento' => '200000' . $i, 'nombre' => 'C', 'primer_apellido' => 'D', 'celular' => '3001234567',
            'direccion' => 'y', 'municipio_id' => Municipio::where('codigo_dane', '11001')->value('id')]);
        $dest->empresa_id = $this->demo->id; $dest->save();
        app(Rndc::class)->marcarManual($dest, '200' . $i);

        $remesa = new RemesaCarga(['fecha' => today()->subDays(2), 'operacion' => 'G', 'empaque_codigo' => '6', 'naturaleza' => 1,
            'producto_codigo' => '001504', 'producto_descripcion' => 'ACEITE', 'cantidad' => 20000, 'unidad' => 1,
            'remitente_id' => $remi->id, 'destinatario_id' => $dest->id, 'dueno_poliza' => 'N',
            'horas_pacto_descargue' => 12, 'minutos_pacto_descargue' => 0]);
        $remesa->empresa_id = $this->demo->id; $remesa->save();
        app(Rndc::class)->marcarManual($remesa, '300' . $i);

        $v = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->firstOrFail();
        $v->update(['rndc_configuracion' => 50, 'peso_vacio' => 4000, 'capacidad_kg' => 8000, 'rndc_marca' => '1',
            'rndc_linea' => '373', 'rndc_color' => '9439', 'rndc_carroceria' => '2', 'rndc_combustible' => 1]);
        app(Rndc::class)->marcarManual($v->refresh(), '400' . $i);

        $c = Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->firstOrFail();
        app(Rndc::class)->marcarManual($c, '500' . $i);
        $prop = Propietario::query()->deEmpresa($this->demo)->firstOrFail();
        app(Rndc::class)->marcarManual($prop, '600' . $i);

        $m = new ManifiestoCarga(['tipo' => 'G', 'fecha_expedicion' => today()->subDays(2),
            'origen_id' => Municipio::where('codigo_dane', '76001')->value('id'),
            'destino_id' => Municipio::where('codigo_dane', '11001')->value('id'),
            'vehiculo_id' => $v->id, 'conductor_id' => $c->id, 'titular_propietario_id' => $prop->id,
            'valor_flete' => 3200000, 'anticipo' => 1000000, 'retencion_fuente_pct' => 1, 'reteica_por_mil' => 3,
            'responsable_cargue' => 'E', 'responsable_descargue' => 'E']);
        $m->empresa_id = $this->demo->id; $m->save();
        $remesa->update(['manifiesto_id' => $m->id]);
        app(Rndc::class)->marcarManual($m->refresh(), '700' . $i);

        return $m->refresh();
    }

    public function test_cumplido_remesa_variables_y_validaciones(): void
    {
        $this->entrarComo('admin@demo.test');
        $m = $this->manifiestoRegistrado();
        $r = $m->remesas->first();

        $cum = CumplidoRemesa::create([
            'empresa_id' => $this->demo->id, 'remesa_id' => $r->id, 'tipo' => 'C',
            'cantidad_entregada' => 22000, // mayor que cargada → error
            'llegada_descargue' => today()->setTime(14, 10),
            'entrada_descargue' => today()->setTime(14, 20),
            'salida_descargue' => today()->setTime(16, 20),
        ]);
        $this->assertStringContainsString('mayor que la cantidad cargada', implode(' ', $cum->validarRndc()));

        $cum->update(['cantidad_entregada' => 20000]);
        $this->assertSame([], $cum->validarRndc());

        $v = $cum->variablesRndc($this->demo);
        $this->assertSame('5', (string) $cum->procesoRndc());
        $this->assertSame('C', $v['TIPOCUMPLIDOREMESA']);
        $this->assertSame(20000, $v['CANTIDADENTREGADA']);
        $this->assertSame('14:20', $v['HORAENTRADADESCARGUECUMPLIDO']);
        $this->assertArrayNotHasKey('MOTIVOSUSPENSIONREMESA', $v);
    }

    public function test_suspension_de_remesa_requiere_motivo(): void
    {
        $this->entrarComo('admin@demo.test');
        $m = $this->manifiestoRegistrado();
        $r = $m->remesas->first();
        $cum = CumplidoRemesa::create(['empresa_id' => $this->demo->id, 'remesa_id' => $r->id, 'tipo' => 'S']);
        $this->assertStringContainsString('motivo', implode(' ', $cum->validarRndc()));

        $cum->update(['motivo_suspension' => 'V']);
        $this->assertSame([], $cum->validarRndc());

        $v = $cum->variablesRndc($this->demo);
        $this->assertSame('V', $v['MOTIVOSUSPENSIONREMESA']);
        $this->assertArrayNotHasKey('FECHALLEGADADESCARGUE', $v, 'En suspensión no se reportan tiempos.');
    }

    public function test_cumplido_manifiesto_exige_remesas_cumplidas(): void
    {
        $this->entrarComo('admin@demo.test');
        $m = $this->manifiestoRegistrado();

        $cm = CumplidoManifiesto::create([
            'empresa_id' => $this->demo->id, 'manifiesto_id' => $m->id, 'tipo' => 'C',
            'fecha_entrega_documentos' => today(),
        ]);
        $this->assertStringContainsString('cumplir en el RNDC la remesa', implode(' ', $cm->validarRndc()));

        // Cumple la remesa y la "marca" como registrada en el RNDC.
        $cumR = CumplidoRemesa::create(['empresa_id' => $this->demo->id, 'remesa_id' => $m->remesas->first()->id, 'tipo' => 'C',
            'cantidad_entregada' => 20000, 'llegada_descargue' => today(), 'entrada_descargue' => today(), 'salida_descargue' => today()]);
        app(Rndc::class)->marcarManual($cumR, '888');
        $this->assertSame([], $cm->refresh()->validarRndc());
    }

    public function test_calculo_de_neto_a_pagar_con_adicionales_y_descuentos(): void
    {
        $this->entrarComo('admin@demo.test');
        $m = $this->manifiestoRegistrado();

        $cm = CumplidoManifiesto::create([
            'empresa_id' => $this->demo->id, 'manifiesto_id' => $m->id, 'tipo' => 'C',
            'fecha_entrega_documentos' => today(),
            'valor_adicional_cargue' => 1000000, 'motivo_adicional' => 'O',
            'valor_descuento_flete' => 300000, 'motivo_descuento' => 'F',
            'valor_sobreanticipo' => 200000,
        ]);
        // Flete 3.200.000 + 1.000.000 - 300.000 = 3.900.000
        // Reten fuente 1% = 39.000; reteica 3x1000 = 11.700
        // Anticipo 1.000.000 + sobreanticipo 200.000 = 1.200.000
        // Neto = 3.900.000 - 1.200.000 - 39.000 - 11.700 = 2.649.300
        $this->assertSame(2649300, $cm->valorAPagar());

        $v = $cm->variablesRndc($this->demo);
        $this->assertSame(1000000, $v['VALORADICIONALHORASCARGUE']);
        $this->assertSame('O', $v['MOTIVOVALORADICIONAL']);
        $this->assertSame(300000, $v['VALORDESCUENTOFLETE']);
        $this->assertSame('F', $v['MOTIVOVALORDESCUENTOMANIFIESTO']);
        $this->assertSame(200000, $v['VALORSOBREANTICIPO']);
    }

    public function test_pantalla_y_permisos(): void
    {
        $m = $this->manifiestoRegistrado();
        $this->actingAs($this->usuario('admin@demo.test'));
        $this->get('/app/cootrasec-demo/cumplidos-manifiesto')->assertOk()->assertSee('cumplido');
        $this->get('/app/cootrasec-demo/cumplidos-manifiesto/crear')->assertOk();

        // Sin módulo de carga: no abre.
        $this->demo->update(['modulos' => ModuloEmpresa::porDefecto()]);
        $this->flushSession();
        $this->actingAs($this->usuario('admin@demo.test'));
        $this->get('/app/cootrasec-demo/cumplidos-manifiesto')->assertForbidden();
    }
}
