<?php

namespace Tests\Feature\Transito;

use App\Actions\Transito\ConsultarTransito;
use App\Enums\TipoConsultaTransito as T;
use App\Filament\Admin\Pages\ConsumoConsultas;
use App\Filament\Admin\Pages\ProveedorConsultas;
use App\Filament\App\Pages\Integraciones;
use App\Filament\App\Resources\Conductores\Pages\VerConductor;
use App\Filament\App\Transito\ComparendosRelationManager;
use App\Jobs\ConsultarTransitoJob;
use App\Models\ComparendoTransito;
use App\Models\Conductor;
use App\Models\ConsultaTransito;
use App\Models\ProveedorTransito;
use App\Models\Vehiculo;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class ConsultasTransitoTest extends ConfiguracionTestCase
{
    private const URL = 'https://placapi.test';

    protected function setUp(): void
    {
        parent::setUp();

        ProveedorTransito::query()->create([
            'url' => self::URL, 'api_key' => 'clave-de-prueba', 'activo' => true, 'costo_credito' => 349,
            'precios' => [T::ConductorComparendos->value => 2500, T::ConductorLicencia->value => 1500, T::VehiculoMultas->value => 1500, T::VehiculoRunt->value => 1500],
        ]);
        $this->demo->forceFill([
            'transito_habilitado' => true,
            'transito_tipos' => array_map(fn ($t) => $t->value, T::cases()),
        ])->save();
    }

    private function conductor(): Conductor
    {
        return Conductor::query()->deEmpresa($this->demo)->vinculados()->firstOrFail();
    }

    private function vehiculo(): Vehiculo
    {
        return Vehiculo::query()->deEmpresa($this->demo)->whereNotNull('propietario_id')->firstOrFail();
    }

    private function consultar($sujeto, T $tipo): ConsultaTransito
    {
        return app(ConsultarTransito::class)($sujeto, $tipo, 'manual');
    }

    /** Respuesta del proveedor que se puede cambiar dentro de la misma prueba. */
    private $respuesta;

    private function responder($respuesta): void
    {
        if ($this->respuesta === null) {
            Http::fake(fn () => ($this->respuesta)());
        }
        $this->respuesta = fn () => $respuesta;
    }

    private function fakeComparendos(bool $cache = false): void
    {
        $this->responder(Http::response(['fromCache' => $cache, 'data' => [
            'totalDeuda' => 650000,
            'comparendos' => [
                ['numeroComparendo' => '2300100000012345', 'fecha' => '2026-08-10', 'codigoInfraccion' => 'C02', 'infraccion' => 'Estacionar en sitio prohibido', 'valor' => 650000, 'estado' => 'PENDIENTE DE PAGO', 'placa' => 'DMO101'],
                ['numeroComparendo' => '2300100000099999', 'fecha' => '2025-01-02', 'codigoInfraccion' => 'B01', 'infraccion' => 'Sin licencia', 'valor' => 300000, 'estado' => 'PAGADA'],
            ],
        ]]));
    }

    public function test_consulta_de_comparendos_cobra_guarda_y_avisa(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->fakeComparendos();
        $c = $this->conductor();

        $consulta = $this->consultar($c, T::ConductorComparendos);

        $this->assertSame('ok', $consulta->estado);
        $this->assertTrue($consulta->cobrada);
        $this->assertEquals(2500, $consulta->precio);
        $this->assertEquals(698, $consulta->costo, '2 créditos × 349.');
        Http::assertSent(fn ($r) => $r->hasHeader('x-api-key', 'clave-de-prueba') && $r['docNumber'] === (string) $c->documento);

        $c->refresh();
        $this->assertSame(1, $c->transito_resumen['comparendos']['pendientes']);
        $this->assertEquals(650000, $c->transito_resumen['comparendos']['deuda']);
        $this->assertSame(1, $c->comparendos_cantidad);
        $this->assertSame(2, ComparendoTransito::query()->deEmpresa($this->demo)->count());
        $this->artisan('queue:work', ['--stop-when-empty' => true, '--queue' => 'alta,default,baja', '--memory' => 2048]);
        $this->assertSame(1, $this->usuario('admin@demo.test')->notifications()->where('data', 'like', '%Comparendos nuevos%')->count());

        // La segunda vez no avisa de nuevo; si un comparendo deja de aparecer, se marca.
        $this->responder(Http::response(['data' => ['comparendos' => []]]));
        $this->consultar($c, T::ConductorComparendos);
        $this->assertSame(2, ComparendoTransito::query()->deEmpresa($this->demo)->whereNotNull('ya_no_aparece_at')->count());
        $this->assertSame(0, $c->refresh()->transito_resumen['comparendos']['pendientes']);
    }

    public function test_formato_del_estado_de_cuenta_de_placapi(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->responder(Http::response(['source' => 'simit', 'status' => 'danger', 'mode' => 'live', 'cost' => 2, 'data' => [
            'docNumber' => '1', 'totalDeuda' => 1860416, 'pazSalvo' => false,
            'totalComparendos' => 1, 'deudaComparendos' => 522700,
            'comparendos' => [['comparendoId' => '11001000000012345678', 'fecha' => '2026-05-02', 'organismo' => 'Bogotá', 'infraccion' => 'C14', 'codigo' => 'C14', 'estado' => 'Pendiente', 'valor' => 522700, 'departamento' => '', 'tipo' => 'comparendo']],
            'totalResoluciones' => 1, 'deudaResoluciones' => 837716,
            'resoluciones' => [['comparendoId' => '324', 'fecha' => '2025-11-01', 'organismo' => 'Montería', 'infraccion' => 'D02', 'codigo' => 'D02', 'estado' => 'Cobro coactivo', 'valor' => 837716, 'departamento' => '', 'tipo' => 'resolucion']],
            'totalAcuerdos' => 1, 'pendienteAcuerdos' => 500000,
            'acuerdos' => [['resolucion' => '9876', 'fechaResolucion' => '2025-01-10', 'estado' => 'Vigente', 'valorAcuerdo' => 900000, 'pendiente' => 500000, 'secretaria' => 'Cereté', 'departamento' => '']],
        ]]));
        $c = $this->conductor();

        $this->assertTrue($this->consultar($c, T::ConductorComparendos)->cobrada);
        $r = $c->refresh()->transito_resumen['comparendos'];
        $this->assertSame([3, 2, 1], [$r['total'], $r['pendientes'], $r['acuerdos']]);
        $this->assertEquals(1860416, $r['deuda']);

        // Respuesta desde la caché del proveedor: no se cobra.
        $this->responder(Http::response(['mode' => 'cache', 'data' => ['comparendos' => []]]));
        $this->assertFalse($this->consultar($c, T::ConductorComparendos)->cobrada);
    }

    public function test_sin_datos_cache_y_errores_no_se_cobran(): void
    {
        $this->entrarComo('admin@demo.test');
        $c = $this->conductor();

        $this->responder(Http::response(['message' => 'No encontrado'], 404));
        $sin = $this->consultar($c, T::ConductorLicencia);
        $this->assertSame('sin_datos', $sin->estado);
        $this->assertFalse($sin->cobrada);
        $this->assertEquals(0, $sin->precio);

        $this->responder(Http::response(['message' => 'Sin saldo'], 402));
        $err = $this->consultar($c, T::ConductorLicencia);
        $this->assertSame('error', $err->estado);
        $this->assertStringContainsString('saldo', $err->error);
        $this->assertFalse($err->cobrada);

        $this->responder(Http::response(['fromCache' => true, 'data' => ['comparendos' => []]]));
        $cache = $this->consultar($c, T::ConductorComparendos);
        $this->assertSame('ok', $cache->estado);
        $this->assertFalse($cache->cobrada);

        $this->assertEquals(0, $this->demo->refresh()->gastoTransitoDelMes());
    }

    public function test_tope_mensual_bloquea_todo_y_precio_especial(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->fakeComparendos();
        $this->demo->forceFill(['transito_tope_mensual' => 4000, 'transito_precios' => [T::ConductorComparendos->value => 2000]])->save();
        $c = $this->conductor();

        $this->assertEquals(2000, $this->consultar($c, T::ConductorComparendos)->precio, 'Precio especial de la empresa.');
        $this->consultar($c, T::ConductorComparendos);
        $this->assertEquals(4000, $this->demo->refresh()->gastoTransitoDelMes());

        try {
            $this->consultar($c, T::ConductorLicencia);
            $this->fail('Debió bloquearse por el tope.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tope', $e->errors()['consulta'][0]);
        }
        $this->assertSame(2, ConsultaTransito::count());
    }

    public function test_requisitos_modulo_tipo_y_propietario(): void
    {
        $this->entrarComo('admin@demo.test');
        Http::fake();
        $c = $this->conductor();

        $this->demo->forceFill(['transito_tipos' => [T::ConductorComparendos->value]])->save();
        $this->assertThrows(fn () => $this->consultar($c, T::ConductorLicencia), ValidationException::class, 'no tiene activa');

        $this->assertThrows(fn () => $this->consultar($c, T::VehiculoRunt), ValidationException::class);

        $this->demo->forceFill(['transito_habilitado' => false])->save();
        $this->assertThrows(fn () => $this->consultar($c, T::ConductorComparendos), ValidationException::class, 'no está habilitado');
        Http::assertNothingSent();
    }

    public function test_licencia_suspendida_bloquea_el_fuec_si_la_empresa_lo_activa(): void
    {
        $this->entrarComo('admin@demo.test');
        $c = $this->conductor();
        $this->assertSame([], $c->bloqueosTransito());

        Http::fake([self::URL . '/api/licencia' => Http::response(['data' => ['driverStatus' => 'ACTIVO', 'licenses' => [
            ['category' => 'C2', 'status' => 'SUSPENDIDA', 'dueDate' => '2028-01-01'],
        ]]])]);
        $this->consultar($c, T::ConductorLicencia);
        Http::assertSent(fn ($r) => filled($r['primerApellido'] ?? null));

        $c->refresh();
        $this->assertTrue($c->transito_resumen['licencia']['suspendida']);
        $this->assertStringContainsString('SUSPENDIDA', implode(' ', $c->bloqueosOperacion()));

        $this->demo->forceFill(['transito_bloquear_licencia' => false])->save();
        $this->assertSame([], Conductor::find($c->id)->bloqueosTransito(), 'Con el interruptor apagado no bloquea.');

        // Comparendos: solo si se activa y desde el valor definido.
        $c->forceFill(['transito_resumen' => [...$c->transito_resumen, 'comparendos' => ['pendientes' => 1, 'acuerdos' => 0, 'deuda' => 300000, 'total' => 1, 'consultado_at' => now()->toIso8601String()]]])->save();
        $this->assertSame([], Conductor::find($c->id)->bloqueosTransito());
        $this->demo->forceFill(['transito_bloquear_comparendos' => true, 'transito_bloquear_desde' => 500000])->save();
        $this->assertSame([], Conductor::find($c->id)->bloqueosTransito(), 'Deuda menor que el mínimo.');
        $this->demo->forceFill(['transito_bloquear_desde' => 200000])->save();
        $this->assertCount(1, Conductor::find($c->id)->bloqueosTransito());
    }

    public function test_runt_del_vehiculo_usa_el_propietario(): void
    {
        $this->entrarComo('admin@demo.test');
        $v = $this->vehiculo();
        Http::fake([self::URL . '/api/consulta' => Http::response(['data' => [
            'informacionGeneral' => ['estado' => 'ACTIVO'],
            'soat' => [['fechaVencimiento' => '2027-05-01', 'estado' => 'VIGENTE']],
            'tecnoMecanica' => [['fechaVencimiento' => '2027-03-01', 'vigente' => 'SI']],
        ]])]);

        $consulta = $this->consultar($v, T::VehiculoRunt);

        $this->assertSame($v->placa, $consulta->referencia);
        Http::assertSent(fn ($r) => $r['placa'] === strtoupper($v->placa) && $r['docNumber'] === (string) $v->propietario()->withoutGlobalScopes()->first()->documento);
        $this->assertSame('2027-05-01', $v->refresh()->transito_resumen['runt']['soat_vence']);
    }

    public function test_automaticas_solo_si_la_empresa_las_activa_y_para_los_marcados(): void
    {
        Queue::fake();
        $c = $this->conductor();
        $c->forceFill(['transito_auto' => true])->save();

        $this->artisan('ruta:consultas-transito')->assertSuccessful();
        Queue::assertNothingPushed();

        $this->demo->forceFill(['transito_auto' => true, 'transito_auto_tipos' => [T::ConductorComparendos->value], 'transito_frecuencia_dias' => 15])->save();
        $this->artisan('ruta:consultas-transito')->assertSuccessful();
        Queue::assertPushed(ConsultarTransitoJob::class, 1);

        // Con una consulta reciente no se repite.
        ConsultaTransito::query()->create(['empresa_id' => $this->demo->id, 'tipo' => T::ConductorComparendos, 'consultable_type' => $c->getMorphClass(),
            'consultable_id' => $c->id, 'referencia' => 'x', 'origen' => 'automatica', 'estado' => 'ok']);
        $this->artisan('ruta:consultas-transito')->assertSuccessful();
        Queue::assertPushed(ConsultarTransitoJob::class, 1);
    }

    public function test_pantallas_de_la_empresa_y_permisos(): void
    {
        $this->fakeComparendos();
        $c = $this->conductor();

        $this->entrarComo('auxiliar@demo.test');
        Livewire::test(ComparendosRelationManager::class, ['ownerRecord' => $c, 'pageClass' => VerConductor::class])
            ->assertOk()->assertSee('Aún no se ha consultado')
            ->callTableAction('consultar_conductor_comparendos')
            ->assertNotified('Consulta realizada')
            ->assertSee('1 pendiente(s) por $ 650.000')
            ->assertDontSee('Aún no se ha consultado');
        $this->assertSame(1, ConsultaTransito::count());

        // Las dos pestañas cargan con datos (comparendos e historial), en conductor y en vehículo.
        Livewire::test(ComparendosRelationManager::class, ['ownerRecord' => $c->refresh(), 'pageClass' => VerConductor::class])
            ->assertOk()->assertSee('2300100000012345');
        Livewire::test(\App\Filament\App\Transito\ConsultasRelationManager::class, ['ownerRecord' => $c, 'pageClass' => VerConductor::class])
            ->assertOk()->assertSee('Con datos')->assertSee('$ 2.500');
        $v = $this->vehiculo();
        foreach ([ComparendosRelationManager::class, \App\Filament\App\Transito\ConsultasRelationManager::class] as $rm) {
            Livewire::test($rm, ['ownerRecord' => $v, 'pageClass' => \App\Filament\App\Resources\Vehiculos\Pages\VerVehiculo::class])->assertOk();
        }
        $this->actingAs($this->usuario('auxiliar@demo.test'));
        $this->get('/app/cootrasec-demo/conductores/' . $c->id)->assertOk();

        $this->entrarComo('admin@demo.test');
        Livewire::test(Integraciones::class)
            ->assertSee('Consultas de tránsito')
            ->set('data.transito_tope_mensual', 50000)
            ->set('data.transito_auto', true)
            ->set('data.transito_auto_tipos', [T::VehiculoRunt->value])
            ->call('guardar')->assertHasNoErrors();
        $this->demo->refresh();
        $this->assertEquals(50000, $this->demo->transito_tope_mensual);
        $this->assertTrue($this->demo->transito_auto);

        // Gerencia ve pero no consulta.
        $this->assertTrue($this->entrarComo('gerencia@demo.test')->can('ver_consultas_transito'));
        $this->assertFalse($this->usuario('gerencia@demo.test')->can('crear_consultas_transito'));

        // Sin el módulo habilitado, no aparece.
        $this->demo->forceFill(['transito_habilitado' => false])->save();
        $this->entrarComo('admin@demo.test');
        $this->assertFalse(ComparendosRelationManager::canViewForRecord($c->refresh(), VerConductor::class));
        Livewire::test(Integraciones::class)->assertDontSee('Consultas que usa la empresa');
    }

    public function test_superadmin_configura_proveedor_y_ve_el_consumo(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->fakeComparendos();
        $this->consultar($this->conductor(), T::ConductorComparendos);

        $this->actingAs($this->usuario('superadmin@ruta.test'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get('/admin/consultas-transito')->assertOk()->assertDontSee('clave-de-prueba');
        $this->get('/admin/consumo-consultas')->assertOk()->assertSee($this->demo->razon_social)->assertSee('$ 2.500');

        Livewire::test(ConsumoConsultas::class)->assertSee('$ 1.802')->callAction('excel')->assertFileDownloaded();
        Livewire::test(ProveedorConsultas::class)->assertOk();

        $this->get('/admin/empresas/' . $this->demo->id . '/editar')->assertOk()->assertSee('Consultas de tránsito');

        $this->actingAs($this->usuario('admin@demo.test'));
        $this->get('/admin/consumo-consultas')->assertForbidden();
    }
}
