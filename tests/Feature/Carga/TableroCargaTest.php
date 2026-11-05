<?php

namespace Tests\Feature\Carga;

use App\Enums\ModuloEmpresa;
use App\Filament\App\Gerencia\Widgets\CargaEstadoRndc;
use App\Filament\App\Gerencia\Widgets\ResumenCarga;
use App\Filament\App\Gerencia\Widgets\TendenciaCarga;
use App\Filament\App\Pages\Inicio;
use App\Filament\App\Pages\TableroGerencia;
use App\Filament\App\Widgets\InicioCarga;
use App\Models\Conductor;
use App\Models\CumplidoManifiesto;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\RemesaCarga;
use App\Models\TerceroCarga;
use App\Models\TipoIdentificacion;
use App\Models\Vehiculo;
use App\Support\Gerencia\Indicadores;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Tablero de carga: indicadores en gerencia y lo accionable en Inicio.
 *
 * Lo que de verdad hay que fijar es que estos widgets no se cuelen en una empresa
 * que no tiene contratado el módulo, ni en un usuario sin permiso.
 */
class TableroCargaTest extends ConfiguracionTestCase
{
    private function conCarga(): void
    {
        $this->demo->update(['modulos' => [...ModuloEmpresa::porDefecto(), 'carga']]);
    }

    private function manifiestoConRemesa(int $flete = 3000000): ManifiestoCarga
    {
        $cc = TipoIdentificacion::where('codigo', 'CC')->value('id');
        $n = (string) (RemesaCarga::query()->withoutGlobalScopes()->count() + 1);

        $remitente = TerceroCarga::create(['empresa_id' => $this->demo->id, 'tipo_identificacion_id' => $cc,
            'documento' => '6600'.$n, 'nombre' => 'Remitente', 'primer_apellido' => 'Uno', 'celular' => '3001234567',
            'direccion' => 'Calle 1', 'municipio_id' => Municipio::where('codigo_dane', '23001')->value('id')]);
        $destinatario = TerceroCarga::create(['empresa_id' => $this->demo->id, 'tipo_identificacion_id' => $cc,
            'documento' => '6700'.$n, 'nombre' => 'Destinatario', 'primer_apellido' => 'Dos', 'celular' => '3001234567',
            'direccion' => 'Calle 2', 'municipio_id' => Municipio::where('codigo_dane', '11001')->value('id')]);

        $remesa = RemesaCarga::create(['empresa_id' => $this->demo->id, 'fecha' => today(), 'operacion' => 'G',
            'empaque_codigo' => '6', 'naturaleza' => 1, 'producto_codigo' => '001504', 'producto_descripcion' => 'Maíz',
            'cantidad' => 15000, 'unidad' => 1, 'remitente_id' => $remitente->id,
            'destinatario_id' => $destinatario->id, 'dueno_poliza' => 'N']);

        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->first();
        $vehiculo->update(['rndc_configuracion' => 50, 'peso_vacio' => 4000]);

        $manifiesto = ManifiestoCarga::create([
            'empresa_id' => $this->demo->id, 'tipo' => 'G', 'fecha_expedicion' => today(),
            'origen_id' => Municipio::where('codigo_dane', '23001')->value('id'),
            'destino_id' => Municipio::where('codigo_dane', '11001')->value('id'),
            'vehiculo_id' => $vehiculo->id,
            'conductor_id' => Conductor::query()->deEmpresa($this->demo)->value('id'),
            'titular_propietario_id' => Propietario::query()->deEmpresa($this->demo)->value('id'),
            'valor_flete' => $flete, 'anticipo' => 500000,
            'responsable_cargue' => 'E', 'responsable_descargue' => 'E',
        ]);

        $remesa->update(['manifiesto_id' => $manifiesto->id]);

        return $manifiesto->fresh();
    }

    public function test_los_indicadores_de_carga_cuentan_lo_del_periodo(): void
    {
        $this->conCarga();
        $this->entrarComo('admin@demo.test');
        $this->manifiestoConRemesa(3000000);
        $this->manifiestoConRemesa(2000000);

        $c = (new Indicadores($this->demo->id, today()->startOfMonth(), today()->endOfMonth()))->carga();

        $this->assertSame(2, $c['manifiestos']);
        $this->assertSame(2, $c['remesas']);
        $this->assertSame(5000000, $c['flete']);
        $this->assertSame(30.0, $c['toneladas']);
        $this->assertSame(2, $c['sin_cumplir']);
        $this->assertSame(2, $c['tiempos_incompletos']);
    }

    public function test_los_saldos_fuera_de_plazo_se_cuentan_aparte(): void
    {
        $this->conCarga();
        $this->entrarComo('admin@demo.test');
        $manifiesto = $this->manifiestoConRemesa();

        CumplidoManifiesto::create(['empresa_id' => $this->demo->id, 'manifiesto_id' => $manifiesto->id,
            'tipo' => 'C', 'fecha_entrega_documentos' => today()->subMonth()]);

        $c = (new Indicadores($this->demo->id, today()->startOfMonth(), today()->endOfMonth()))->carga();

        $this->assertSame(1, $c['saldo_vencido']);
        $this->assertGreaterThan(0, $c['saldo_vencido_valor']);
        $this->assertSame(1, $c['cumplidos']);
    }

    public function test_la_tendencia_devuelve_doce_meses(): void
    {
        $this->conCarga();
        $this->entrarComo('admin@demo.test');
        $this->manifiestoConRemesa(4000000);

        $t = (new Indicadores($this->demo->id, today()->startOfMonth(), today()->endOfMonth()))->tendenciaCarga();

        $this->assertCount(12, $t['etiquetas']);
        $this->assertCount(12, $t['flete']);
        $this->assertSame(4000000, end($t['flete']));
    }

    public function test_el_tablero_de_gerencia_muestra_la_carga(): void
    {
        $this->conCarga();
        $this->entrarComo('gerencia@demo.test');
        $this->manifiestoConRemesa();

        $this->get(TableroGerencia::getUrl())->assertOk();

        Livewire::test(ResumenCarga::class)->assertOk();
        Livewire::test(CargaEstadoRndc::class)->assertOk();
        Livewire::test(TendenciaCarga::class)->assertOk();
    }

    public function test_inicio_muestra_lo_accionable_de_carga(): void
    {
        $this->conCarga();
        $this->entrarComo('admin@demo.test');
        $this->manifiestoConRemesa();

        $this->get(Inicio::getUrl())->assertOk();

        Livewire::test(InicioCarga::class)
            ->assertOk()
            ->assertSee('Viajes en curso')
            ->assertSee('Tiempos sin reportar');
    }

    public function test_sin_el_modulo_contratado_los_widgets_no_se_ven(): void
    {
        // Sin «carga» en los módulos de la empresa: ni gerencia ni inicio deben mostrarlos.
        $this->demo->update(['modulos' => ModuloEmpresa::porDefecto()]);
        $this->entrarComo('gerencia@demo.test');

        $this->assertFalse(ResumenCarga::canView());
        $this->assertFalse(CargaEstadoRndc::canView());
        $this->assertFalse(TendenciaCarga::canView());
        $this->assertFalse(InicioCarga::canView());
    }

    public function test_un_usuario_sin_permiso_de_carga_no_ve_los_widgets(): void
    {
        $this->conCarga();
        $this->entrarComo('auxiliar@demo.test');

        // El auxiliar no tiene ver_gerencia, así que el tablero queda fuera de su alcance.
        $this->assertFalse(ResumenCarga::canView());
        $this->assertFalse(TendenciaCarga::canView());
    }
}
