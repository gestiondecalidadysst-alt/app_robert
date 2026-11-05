<?php

namespace Tests\Feature\Carga;

use App\Enums\ModuloEmpresa;
use App\Models\Conductor;
use App\Models\CumplidoManifiesto;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\Vehiculo;
use Illuminate\Support\Carbon;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Dos exigencias de la normativa de 2026: el manifiesto cubre también rutas
 * municipales (antes exentas) y el saldo se paga dentro de los cinco días hábiles
 * siguientes al cumplido.
 */
class PlazosYRutasTest extends ConfiguracionTestCase
{
    private function manifiesto(?int $origen = null, ?int $destino = null, int $flete = 2000000, int $anticipo = 0): ManifiestoCarga
    {
        $this->demo->update(['modulos' => [...ModuloEmpresa::porDefecto(), 'carga']]);
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->first();
        $vehiculo->update(['rndc_configuracion' => 50, 'peso_vacio' => 4000]);

        return ManifiestoCarga::create([
            'empresa_id' => $this->demo->id, 'tipo' => 'G', 'fecha_expedicion' => today(),
            'origen_id' => $origen ?? Municipio::where('codigo_dane', '23001')->value('id'),
            'destino_id' => $destino ?? Municipio::where('codigo_dane', '11001')->value('id'),
            'vehiculo_id' => $vehiculo->id,
            'conductor_id' => Conductor::query()->deEmpresa($this->demo)->value('id'),
            'titular_propietario_id' => Propietario::query()->deEmpresa($this->demo)->value('id'),
            'valor_flete' => $flete, 'anticipo' => $anticipo,
            'responsable_cargue' => 'E', 'responsable_descargue' => 'E',
        ]);
    }

    public function test_se_admite_una_ruta_municipal_con_el_mismo_origen_y_destino(): void
    {
        $monteria = Municipio::where('codigo_dane', '23001')->value('id');
        $manifiesto = $this->manifiesto($monteria, $monteria);

        // La Resolución 20263040016075 de 2026 incorporó las rutas municipales,
        // antes exentas: nada puede rechazarlas por tener el mismo municipio.
        $errores = implode(' ', $manifiesto->validarRndc());

        $this->assertStringNotContainsString('municipio', mb_strtolower($errores));
        $this->assertStringNotContainsString('origen', mb_strtolower($errores));
    }

    public function test_el_plazo_del_saldo_son_cinco_dias_habiles_desde_el_cumplido(): void
    {
        $manifiesto = $this->manifiesto();

        // Cumplido un lunes: cinco hábiles son el lunes siguiente (se saltan sábado y domingo).
        CumplidoManifiesto::create([
            'empresa_id' => $this->demo->id, 'manifiesto_id' => $manifiesto->id, 'tipo' => 'C',
            'fecha_entrega_documentos' => Carbon::parse('2026-11-02'), // lunes
        ]);

        $limite = $manifiesto->fresh()->fechaLimitePagoSaldo();

        $this->assertSame('2026-11-09', $limite?->toDateString());
        $this->assertTrue($limite->isMonday());
    }

    public function test_sin_cumplido_el_plazo_todavia_no_corre(): void
    {
        $manifiesto = $this->manifiesto();

        $this->assertNull($manifiesto->fechaLimitePagoSaldo());
        $this->assertSame('sin_cumplido', $manifiesto->estadoPagoSaldo());
    }

    public function test_un_saldo_fuera_de_plazo_queda_marcado_como_vencido(): void
    {
        $manifiesto = $this->manifiesto();
        CumplidoManifiesto::create([
            'empresa_id' => $this->demo->id, 'manifiesto_id' => $manifiesto->id, 'tipo' => 'C',
            'fecha_entrega_documentos' => today()->subMonth(),
        ]);

        $this->assertSame('vencido', $manifiesto->fresh()->estadoPagoSaldo());
    }

    public function test_un_manifiesto_sin_saldo_pendiente_no_tiene_plazo_que_vencer(): void
    {
        // Anticipo igual al flete menos las retenciones: no queda saldo.
        $manifiesto = $this->manifiesto(flete: 1000000, anticipo: 1000000);
        CumplidoManifiesto::create([
            'empresa_id' => $this->demo->id, 'manifiesto_id' => $manifiesto->id, 'tipo' => 'C',
            'fecha_entrega_documentos' => today()->subMonth(),
        ]);

        $this->assertSame(0, $manifiesto->fresh()->saldo());
        $this->assertSame('pagado', $manifiesto->fresh()->estadoPagoSaldo());
    }
}
