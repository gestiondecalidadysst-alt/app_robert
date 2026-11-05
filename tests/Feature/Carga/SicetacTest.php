<?php

namespace Tests\Feature\Carga;

use App\Enums\ModuloEmpresa;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\TarifaSicetac;
use App\Models\Vehiculo;
use App\Support\Carga\Sicetac;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Costo mínimo del SICE-TAC.
 *
 * El Ministerio no publica un servicio de consulta, así que el mínimo sale de las
 * tarifas que carga la empresa. Lo importante es que no dependa de un número
 * escrito a mano y que dejar el campo vacío no sirva para saltarse el tope.
 */
class SicetacTest extends ConfiguracionTestCase
{
    private int $monteria;

    private int $bogota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->demo->update(['modulos' => [...ModuloEmpresa::porDefecto(), 'carga']]);
        $this->monteria = Municipio::where('codigo_dane', '23001')->value('id');
        $this->bogota = Municipio::where('codigo_dane', '11001')->value('id');
    }

    private function tarifa(array $extra = []): TarifaSicetac
    {
        return TarifaSicetac::create([
            'empresa_id' => $this->demo->id,
            'origen_id' => $this->monteria,
            'destino_id' => $this->bogota,
            'configuracion' => null,
            'valor_minimo' => 3000000,
            'vigente_desde' => today()->subMonth(),
            ...$extra,
        ]);
    }

    private function manifiesto(int $flete, ?int $minimoDigitado = null): ManifiestoCarga
    {
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->first();
        $vehiculo->update(['rndc_configuracion' => 50, 'peso_vacio' => 4000]);

        return ManifiestoCarga::create([
            'empresa_id' => $this->demo->id, 'tipo' => 'G', 'fecha_expedicion' => today(),
            'origen_id' => $this->monteria, 'destino_id' => $this->bogota,
            'vehiculo_id' => $vehiculo->id,
            'conductor_id' => Conductor::query()->deEmpresa($this->demo)->value('id'),
            'titular_propietario_id' => Propietario::query()->deEmpresa($this->demo)->value('id'),
            'valor_flete' => $flete, 'valor_minimo_sicetac' => $minimoDigitado,
            'anticipo' => 0, 'responsable_cargue' => 'E', 'responsable_descargue' => 'E',
        ]);
    }

    public function test_sin_tarifa_cargada_no_se_inventa_un_minimo(): void
    {
        $this->assertNull(Sicetac::minimo($this->demo->id, $this->monteria, $this->bogota, 50));
    }

    public function test_toma_la_tarifa_de_la_ruta(): void
    {
        $this->tarifa();

        $this->assertSame(3000000, Sicetac::minimo($this->demo->id, $this->monteria, $this->bogota, 50));
    }

    public function test_la_tarifa_de_la_configuracion_exacta_manda_sobre_la_generica(): void
    {
        $this->tarifa();                                        // cualquiera: 3.000.000
        $this->tarifa(['configuracion' => 50, 'valor_minimo' => 3600000]);

        $this->assertSame(3600000, Sicetac::minimo($this->demo->id, $this->monteria, $this->bogota, 50));
        $this->assertSame(3000000, Sicetac::minimo($this->demo->id, $this->monteria, $this->bogota, 54));
    }

    public function test_una_tarifa_vencida_no_aplica(): void
    {
        $this->tarifa(['vigente_hasta' => today()->subDay()]);

        $this->assertNull(Sicetac::minimo($this->demo->id, $this->monteria, $this->bogota, 50));
    }

    public function test_el_flete_por_debajo_del_minimo_no_deja_radicar_aunque_el_campo_este_vacio(): void
    {
        $this->tarifa();
        $manifiesto = $this->manifiesto(flete: 2000000);

        $errores = implode(' ', $manifiesto->validarRndc());

        $this->assertStringContainsString('SICE-TAC', $errores);
        $this->assertStringContainsString('3.000.000', $errores);
    }

    public function test_el_flete_sobre_el_minimo_pasa(): void
    {
        $this->tarifa();
        $manifiesto = $this->manifiesto(flete: 3200000);

        $this->assertStringNotContainsString('SICE-TAC', implode(' ', $manifiesto->validarRndc()));
    }

    public function test_el_valor_digitado_manda_sobre_la_tarifa(): void
    {
        $this->tarifa();
        $manifiesto = $this->manifiesto(flete: 2500000, minimoDigitado: 2400000);

        $this->assertSame(2400000, $manifiesto->minimoSicetac());
        $this->assertStringNotContainsString('SICE-TAC', implode(' ', $manifiesto->validarRndc()));
    }

    public function test_la_carga_desde_csv_lee_por_codigo_dane(): void
    {
        [$cargadas, $errores] = Sicetac::importar($this->demo->id, <<<'CSV'
            origen;destino;configuracion;valor;desde;hasta
            23001;11001;50;2850000;2026-01-01;
            76001;11001;;3100000;;
            CSV);

        $this->assertSame(2, $cargadas);
        $this->assertSame([], $errores);
        $this->assertSame(2850000, Sicetac::minimo($this->demo->id, $this->monteria, $this->bogota, 50));
    }

    public function test_la_carga_informa_de_las_lineas_malas_sin_perder_las_buenas(): void
    {
        [$cargadas, $errores] = Sicetac::importar($this->demo->id, <<<'CSV'
            23001;11001;50;2850000;2026-01-01;
            99999;11001;50;2000000;;
            23001;11001;50;cero;;
            CSV);

        $this->assertSame(1, $cargadas);
        $this->assertCount(2, $errores);
        $this->assertStringContainsString('DANE', $errores[0]);
        $this->assertStringContainsString('número', $errores[1]);
    }

    public function test_cargar_la_misma_ruta_actualiza_en_vez_de_duplicar(): void
    {
        Sicetac::importar($this->demo->id, '23001;11001;50;2850000;2026-01-01;');
        Sicetac::importar($this->demo->id, '23001;11001;50;2990000;2026-01-01;');

        $this->assertSame(1, TarifaSicetac::query()->deEmpresa($this->demo)->count());
        $this->assertSame(2990000, Sicetac::minimo($this->demo->id, $this->monteria, $this->bogota, 50));
    }

    public function test_las_tarifas_no_se_comparten_entre_empresas(): void
    {
        $this->tarifa();
        $otra = Empresa::query()->where('id', '!=', $this->demo->id)->first()
            ?? Empresa::factory()->create();

        $this->assertNull(Sicetac::minimo($otra->id, $this->monteria, $this->bogota, 50));
    }
}
