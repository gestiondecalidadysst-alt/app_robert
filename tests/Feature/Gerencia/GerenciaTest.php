<?php

namespace Tests\Feature\Gerencia;

use App\Filament\App\Gerencia\Widgets\ResumenCartera;
use App\Filament\App\Pages\Informes;
use App\Filament\App\Pages\TableroGerencia;
use App\Filament\App\Widgets\ExtractosDeHoy;
use App\Filament\App\Widgets\InicioOperacion;
use App\Filament\App\Widgets\InicioPagosPorTipo;
use App\Filament\App\Widgets\InicioRecaudoMes;
use App\Filament\App\Widgets\PendientesPorCobrar;
use App\Filament\App\Widgets\UltimosPagos;
use App\Mail\CorreoInformeGerencia;
use App\Models\Contrato;
use App\Models\Fuec;
use App\Models\Inspeccion;
use App\Models\Oficina;
use App\Models\WebhookEntrega;
use App\Support\Gerencia\ExportarInforme;
use App\Support\Gerencia\Indicadores;
use App\Support\Gerencia\InformeGerencia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class GerenciaTest extends ConfiguracionTestCase
{
    private function mes(): Indicadores
    {
        return new Indicadores($this->demo->id, today()->startOfMonth(), today()->endOfMonth());
    }

    public function test_indicadores_de_cartera_operacion_y_flota(): void
    {
        $this->entrarComo('admin@demo.test');
        $i = $this->mes();

        // Demo: escolar de 18 M con abono de 6 M; empresarial de 4,5 M pagado completo.
        $c = $i->cartera();
        $this->assertEquals(10500000, $c['recaudado']);
        $this->assertEquals(10500000, $c['recaudo_hoy']);
        $this->assertEquals(12000000, $c['cartera']);
        $this->assertSame(1, $c['contratos_con_saldo']);
        $this->assertEquals(22500000, $c['contratado']);
        $this->assertEquals(12000000, array_sum($c['edades']));

        // Con la fecha límite vencida hace 40 días, el saldo pasa a 31–60.
        Contrato::query()->deEmpresa($this->demo)->where('numero', 1)->update(['fecha_limite_pago' => today()->subDays(40)]);
        $this->assertEquals(12000000, $this->mes()->cartera()['edades']['d31_60']);

        $o = $i->operacion();
        $this->assertSame(1, $o['extractos']);
        $this->assertSame(2, $o['servicios'], 'El servicio del FUEC demo y el planificado.');
        $this->assertSame(1, $o['servicios_sin_asignar']);
        $this->assertSame('Colegio Demo del Sinú', $o['clientes'][0]['cliente']);

        $f = $i->flota();
        $this->assertGreaterThan(0, $f['vehiculos']);
        $this->assertSame($f['vehiculos'], $f['operando'] + $f['suspendidos'] + $f['taller']);

        // Oficina sin contratos: la cartera queda en cero.
        $oficina = Oficina::query()->deEmpresa($this->demo)->where('nombre', 'Montería Oppal')->firstOrFail();
        $this->assertEquals(0, (new Indicadores($this->demo->id, today()->startOfMonth(), today()->endOfMonth(), $oficina->id))->cartera()['cartera']);
    }

    public function test_cumplimiento_del_preoperacional_por_dia_con_extracto(): void
    {
        $this->entrarComo('admin@demo.test');
        Inspeccion::query()->delete();
        $fuec = Fuec::query()->deEmpresa($this->demo)->firstOrFail();
        $i = $this->mes();

        $this->assertSame([1, 0], $i->cumplimientoPreoperacional(today(), today()));

        $preop = new Inspeccion([
            'tipo' => 'preoperacional', 'vehiculo_id' => $fuec->vehiculo_id, 'fecha' => today(), 'resultado' => 'apto',
            'respuestas' => [], 'datos' => [], 'codigo_verificacion' => Str::lower(Str::random(24)),
        ]);
        $preop->empresa_id = $this->demo->id;
        $preop->save();

        $this->assertSame([1, 1], $i->cumplimientoPreoperacional(today(), today()));
        $this->assertSame(100, $this->mes()->pesv()['preop_cumplimiento']);

        $t = $this->mes()->tendencias();
        $this->assertCount(12, $t['meses']);
        $this->assertSame(100, end($t['preoperacional']));
        $this->assertEquals(10500000, end($t['recaudo']));
    }

    public function test_tablero_widgets_e_informes(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->actingAs($this->usuario('admin@demo.test'));

        $this->get('/app/cootrasec-demo/gerencia')->assertOk()->assertSee('Tablero de gerencia');
        $this->get('/app/cootrasec-demo/informes')->assertOk();

        foreach ((new TableroGerencia)->getWidgets() as $widget) {
            // Los widgets de módulos que la empresa no tiene contratados (carga) no se
            // renderizan a propósito: se comprueban en las pruebas de su propio módulo.
            if (! $widget::canView()) {
                continue;
            }

            Livewire::test($widget, ['pageFilters' => ['periodo' => 'anio']])->assertOk();
        }
        Livewire::test(ResumenCartera::class, ['pageFilters' => ['periodo' => 'mes']])->assertSee('$ 12.000.000');

        foreach (array_keys(ExportarInforme::INFORMES) as $informe) {
            $ruta = ExportarInforme::excel($informe, $this->demo->id, today()->startOfMonth(), today()->endOfMonth());
            $this->assertSame('PK', substr(file_get_contents($ruta), 0, 2), "Excel de {$informe}");
        }
        $this->assertStringStartsWith('%PDF', InformeGerencia::pdf($this->mes()));

        Livewire::test(Informes::class)->set('data.informe', 'contratos')->call('descargar')->assertFileDownloaded();
        Livewire::test(Informes::class)->set('data.informe', 'resumen')->call('descargar')->assertFileDownloaded();
    }

    public function test_datos_de_las_graficas_e_inicio_operativo(): void
    {
        $this->entrarComo('admin@demo.test');
        $i = $this->mes();

        $t = $i->pagosPorTipo();
        $this->assertSame([1, 0, 1], [$t['abono']['n'], $t['abono_final']['n'], $t['total']['n']], 'Demo: un abono y un pago total.');
        $this->assertEquals(6000000, $t['abono']['valor']);

        $s = $i->serie();
        $this->assertCount(today()->daysInMonth, $s['etiquetas']);
        $this->assertEquals(10500000, array_sum($s['recaudo']));
        $this->assertSame(1, array_sum($s['extractos']));
        $this->assertCount(12, (new Indicadores($this->demo->id, today()->startOfYear(), today()->endOfYear()))->serie()['etiquetas'], 'Más de 62 días: por mes.');

        $this->assertSame('Colegio Demo del Sinú', $i->carteraPorCliente()[0]['cliente']);
        $this->assertSame('DMO101', $i->serviciosPorVehiculo()[0]['placa']);

        // Inicio con lo operativo.
        $this->actingAs($this->usuario('admin@demo.test'));
        $this->get('/app/cootrasec-demo')->assertOk();
        foreach ([InicioOperacion::class, InicioPagosPorTipo::class, InicioRecaudoMes::class] as $w) {
            Livewire::test($w)->assertOk();
        }
        Livewire::test(InicioOperacion::class)->assertSee('$ 12.000.000')->assertSee('1 abonos');
        Livewire::test(PendientesPorCobrar::class)->assertSee('Colegio Demo del Sinú')->assertDontSee('Agroindustrial Demo');
        Livewire::test(UltimosPagos::class)->assertSee('Pago total')->assertSee('Abono');
        Livewire::test(ExtractosDeHoy::class)->assertSee('DMO101')->assertSee('Pendiente');
    }

    public function test_gerencia_ve_y_el_auxiliar_no(): void
    {
        $this->actingAs($this->usuario('gerencia@demo.test'));
        $this->get('/app/cootrasec-demo/gerencia')->assertOk();
        $this->get('/app/cootrasec-demo/informes')->assertOk();
    }

    public function test_el_auxiliar_no_ve_el_tablero(): void
    {
        $this->actingAs($this->usuario('auxiliar@demo.test'));
        $this->get('/app/cootrasec-demo/gerencia')->assertForbidden();
        $this->get('/app/cootrasec-demo/informes')->assertForbidden();
    }

    public function test_resumen_mensual_por_correo_y_webhook(): void
    {
        Mail::fake();
        Http::fake();
        $this->demo->update(['webhook_activo' => true, 'webhook_url' => 'https://n8n.test/webhook', 'webhook_eventos' => ['gerencia.informe_mensual']]);

        $destinatarios = InformeGerencia::destinatarios($this->demo)->pluck('email')->all();
        $this->assertContains('gerencia@demo.test', $destinatarios);
        $this->assertContains('admin@demo.test', $destinatarios);
        $this->assertNotContains('auxiliar@demo.test', $destinatarios);

        $this->artisan('ruta:informe-gerencia')->assertSuccessful();

        Mail::assertSent(CorreoInformeGerencia::class, fn (CorreoInformeGerencia $m) => $m->periodo === ucfirst(today()->subMonthNoOverflow()->translatedFormat('F Y')));
        $entrega = WebhookEntrega::query()->where('evento', 'gerencia.informe_mensual')->firstOrFail();
        $this->assertSame(today()->subMonthNoOverflow()->startOfMonth()->toDateString(), $entrega->payload['datos']['desde']);
    }
}
