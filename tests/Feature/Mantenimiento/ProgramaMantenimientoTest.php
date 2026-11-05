<?php

namespace Tests\Feature\Mantenimiento;

use App\Actions\Mantenimiento\AnularMantenimiento;
use App\Actions\Mantenimiento\AvisosPrograma;
use App\Actions\Mantenimiento\GestionarPlanAccion;
use App\Actions\Mantenimiento\PlanDeVehiculo;
use App\Actions\Mantenimiento\ProgramaAnual;
use App\Actions\Mantenimiento\RegistrarMantenimiento;
use App\Enums\EstadoActividad;
use App\Enums\EstadoPrograma;
use App\Filament\App\Resources\Vehiculos\Pages\PlanMantenimientoVehiculo;
use App\Models\Mantenimiento;
use App\Models\PlanAccion;
use App\Models\PlantillaMantenimiento;
use App\Models\RutinaMantenimiento;
use App\Models\Vehiculo;
use App\Models\WebhookEntrega;
use App\Support\Mantenimiento\ExportarPrograma;
use App\Support\Mantenimiento\PlanPreventivo;
use App\Support\Pdf\PdfProgramaMantenimiento;
use App\Support\Webhooks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class ProgramaMantenimientoTest extends ConfiguracionTestCase
{
    private function vehiculo(string $placa = 'DMO102'): Vehiculo
    {
        return Vehiculo::query()->deEmpresa($this->demo)->where('placa', $placa)->firstOrFail();
    }

    private function general(): PlantillaMantenimiento
    {
        return PlantillaMantenimiento::query()->deEmpresa($this->demo)->where('por_defecto', true)->firstOrFail();
    }

    private function rutinaDe(Vehiculo $v, string $nombre = 'Aceite de motor y filtro de aceite'): RutinaMantenimiento
    {
        return PlanPreventivo::rutinasDe($v->refresh())->firstWhere('nombre', $nombre);
    }

    public function test_plantilla_sugerida_por_marca_linea_clase_y_por_defecto(): void
    {
        $v = $this->vehiculo();
        $this->assertSame($this->general()->id, PlanPreventivo::plantillaDe($v)->id);

        $porClase = PlantillaMantenimiento::create(['empresa_id' => $this->demo->id, 'nombre' => 'Busetas', 'criterio' => 'clase', 'clase_vehiculo_id' => $v->clase_vehiculo_id]);
        $this->assertSame($porClase->id, PlanPreventivo::plantillaDe($v)->id);

        $porMarca = PlantillaMantenimiento::create(['empresa_id' => $this->demo->id, 'nombre' => 'Marca', 'criterio' => 'marca_linea', 'marca_id' => $v->marca_id]);
        $this->assertSame($porMarca->id, PlanPreventivo::plantillaDe($v)->id);

        $porLinea = PlantillaMantenimiento::create(['empresa_id' => $this->demo->id, 'nombre' => 'Línea', 'criterio' => 'marca_linea', 'marca_id' => $v->marca_id, 'linea_id' => $v->linea_id]);
        $this->assertSame($porLinea->id, PlanPreventivo::plantillaDe($v)->id);

        $libre = PlantillaMantenimiento::create(['empresa_id' => $this->demo->id, 'nombre' => 'Libre']);
        PlanDeVehiculo::seguirPlantilla($v, $libre);
        $this->assertSame($libre->id, PlanPreventivo::plantillaDe($v->refresh())->id, 'La asignada gana.');
    }

    public function test_plan_personalizado_quitar_agregar_y_copiar(): void
    {
        $v = $this->vehiculo();
        $plan = PlanPreventivo::rutinasDe($v)->reject(fn ($r) => str_starts_with($r->nombre, 'Correa'))
            ->map(fn ($r) => ['nombre' => $r->nombre, 'intervalo_km' => $r->intervalo_km, 'intervalo_meses' => $r->intervalo_meses])->values()->all();
        $plan[] = ['nombre' => 'Aire acondicionado', 'intervalo_meses' => 6];
        PlanDeVehiculo::personalizar($v, $plan);

        $v->refresh();
        $this->assertTrue($v->plan_personalizado);
        $nombres = PlanPreventivo::rutinasDe($v)->pluck('nombre');
        $this->assertContains('Aire acondicionado', $nombres);
        $this->assertNotContains('Correa de distribución o accesorios', $nombres);

        // Quitar desactiva (no borra).
        PlanDeVehiculo::personalizar($v, collect($plan)->reject(fn ($r) => $r['nombre'] === 'Aire acondicionado')->values()->all());
        $this->assertTrue(RutinaMantenimiento::query()->where('vehiculo_id', $v->id)->where('nombre', 'Aire acondicionado')->where('activo', false)->exists());

        $this->assertThrows(fn () => PlanDeVehiculo::personalizar($v, [['nombre' => 'X'], ['nombre' => 'x']]), ValidationException::class);

        $otro = $this->vehiculo('DMO101');
        PlanDeVehiculo::copiarDe($otro, $v);
        $this->assertEquals(PlanPreventivo::rutinasDe($v)->pluck('nombre')->all(), PlanPreventivo::rutinasDe($otro->refresh())->pluck('nombre')->all());

        // Volver a la plantilla.
        PlanDeVehiculo::seguirPlantilla($v, $this->general());
        $this->assertFalse($v->refresh()->plan_personalizado);
        $this->assertCount(10, PlanPreventivo::rutinasDe($v));
    }

    public function test_punto_de_partida_no_cuenta_como_mantenimiento_real(): void
    {
        $v = $this->vehiculo();
        $aceite = $this->rutinaDe($v);
        PlanDeVehiculo::puntoDePartida($v, [$aceite->id => ['fecha' => today()->subMonth()->toDateString(), 'kilometraje' => 70000]], $this->usuario('admin@demo.test'));

        $this->assertSame(0, Mantenimiento::query()->deEmpresa($this->demo)->reales()->where('vehiculo_id', $v->id)->count());
        $fila = PlanPreventivo::para($v)->firstWhere('rutina.id', $aceite->id);
        $this->assertSame(75000, $fila['proximo_km']);
        $this->assertTrue($fila['ultimo']->registro_inicial);
    }

    public function test_proyeccion_por_km_o_meses_lo_que_ocurra_primero(): void
    {
        $rutina = new RutinaMantenimiento(['nombre' => 'Aceite', 'intervalo_km' => 5000, 'intervalo_meses' => 3]);
        $base = Carbon::create(2026, 1, 1);

        // 100 km/día → cada 50 días (antes que 3 meses).
        $o = ProgramaAnual::proyectar($rutina, $base, 10000, 100, $base, Carbon::create(2026, 12, 31));
        $this->assertSame('2026-02-20', $o[0]['fecha']->toDateString());
        $this->assertSame(15000, $o[0]['km']);
        $this->assertSame('2026-04-11', $o[1]['fecha']->toDateString());

        // 20 km/día → cada 3 meses.
        $o = ProgramaAnual::proyectar($rutina, $base, 10000, 20, $base, Carbon::create(2026, 12, 31));
        $this->assertSame(['2026-04-01', '2026-07-01', '2026-10-01'], collect($o)->map(fn ($x) => $x['fecha']->toDateString())->all());

        // Vencida antes de empezar: se programa el primer día.
        $o = ProgramaAnual::proyectar($rutina, Carbon::create(2025, 1, 1), null, 20, Carbon::create(2026, 3, 15), Carbon::create(2026, 12, 31));
        $this->assertSame('2026-03-15', $o[0]['fecha']->toDateString());
        $this->assertTrue($o[0]['atrasada']);

        // Año siguiente: lo de este año se supone hecho a tiempo y el ciclo continúa (no se amontona el 1 de enero).
        $o = ProgramaAnual::proyectar($rutina, Carbon::create(2026, 9, 15), null, 20, Carbon::create(2027, 1, 1), Carbon::create(2027, 12, 31), false);
        $this->assertSame('2027-03-15', $o[0]['fecha']->toDateString());
        $this->assertFalse($o[0]['atrasada']);
    }

    public function test_generar_aprobar_ejecutar_reprogramar_y_versionar(): void
    {
        $this->entrarComo('admin@demo.test');
        $anio = (int) today()->year;
        $v = $this->vehiculo();
        $aceite = $this->rutinaDe($v);
        PlanDeVehiculo::puntoDePartida($v, [$aceite->id => ['fecha' => today()->toDateString(), 'kilometraje' => 80000]]);

        $programa = ProgramaAnual::generar($this->demo, $anio + 1, ['codigo_formato' => 'GMT-FT01'], $this->usuario('admin@demo.test'));
        $this->assertSame(EstadoPrograma::Borrador, $programa->estado);
        $this->assertGreaterThan(0, $programa->actividades()->count());
        $this->assertSame($programa->id, ProgramaAnual::generar($this->demo, $anio + 1)->id, 'Regenerar reutiliza el borrador.');
        $this->assertThrows(fn () => ProgramaAnual::generar($this->demo, $anio + 3), ValidationException::class);

        // Programa del año en curso: aprobación.
        $actual = ProgramaAnual::generar($this->demo, $anio, [], $this->usuario('admin@demo.test'));
        ProgramaAnual::aprobar($actual, $this->usuario('gerencia@demo.test'));
        $actual->refresh();
        $this->assertSame(EstadoPrograma::Aprobado, $actual->estado);
        $this->assertSame('Gerente Demo', $actual->aprobadoPor->name);

        // La próxima actividad de aceite de DMO102 se ejecuta con un mantenimiento real (con 10 días de adelanto).
        $actividad = $actual->actividades()->where('vehiculo_id', $v->id)->where('rutina_nombre', $aceite->nombre)->orderBy('fecha_programada')->first();
        if (! $actividad || $actividad->fecha_programada->year !== $anio) {
            $this->markTestSkipped('Sin actividad de aceite en lo que queda del año.');
        }
        $fecha = $actividad->fecha_programada->copy()->subDays(10)->max(today()->subDays(0));
        $this->travelTo($fecha);
        $m = app(RegistrarMantenimiento::class)($this->demo, $v, [
            'fecha' => $fecha->toDateString(), 'kilometraje' => 84500, 'tipo' => 'preventivo', 'accion' => 'cambio', 'actividad' => 'Cambio de aceite', 'rutinas' => [$aceite->id],
        ], [], $this->usuario('admin@demo.test'));
        $actividad->refresh();
        $this->assertSame(EstadoActividad::Ejecutada, $actividad->estado);
        $this->assertSame($m->id, $actividad->mantenimiento_id);
        $this->assertSame('ejecutada', collect($actividad->historial)->last()['tipo']);

        // Anular el mantenimiento revierte la ejecución.
        app(AnularMantenimiento::class)($m, 'Prueba');
        $this->assertSame(EstadoActividad::Programada, $actividad->refresh()->estado);

        // Reprogramar y cancelar exigen motivo y quedan en el historial.
        $this->assertThrows(fn () => ProgramaAnual::reprogramar($actividad, $fecha->copy()->addDays(5), ''), ValidationException::class);
        ProgramaAnual::reprogramar($actividad, $fecha->copy()->addDays(5), 'Vehículo en servicio', $this->usuario('admin@demo.test'));
        $this->assertTrue($actividad->refresh()->reprogramada());
        $this->assertStringContainsString('Vehículo en servicio', collect($actividad->historial)->last()['detalle']);
        ProgramaAnual::cancelar($actividad, 'Cambio de plan', $this->usuario('admin@demo.test'));
        $this->assertSame(EstadoActividad::Cancelada, $actividad->refresh()->estado);

        // Nueva versión: al aprobarla, la anterior queda reemplazada y lo cancelado/ejecutado pasa a la nueva.
        $v2 = ProgramaAnual::generar($this->demo, $anio, [], $this->usuario('admin@demo.test'));
        $this->assertSame(2, $v2->version);
        ProgramaAnual::aprobar($v2, $this->usuario('gerencia@demo.test'));
        $this->assertSame(EstadoPrograma::Reemplazado, $actual->refresh()->estado);
        $this->assertSame($v2->id, $actividad->refresh()->programa_mantenimiento_id);
        $this->assertSame($v2->id, ProgramaAnual::programaVigente($this->demo->id, $anio)->id);

        // Indicadores, PDF y Excel.
        $ind = ProgramaAnual::indicadores($v2);
        $this->assertCount(4, $ind);
        $this->assertStringStartsWith('%PDF', PdfProgramaMantenimiento::generar($v2));
        $this->assertFileExists(ExportarPrograma::excel($v2));
    }

    public function test_pantallas_del_programa_plantillas_calendario_y_plan_del_vehiculo(): void
    {
        $programa = ProgramaAnual::generar($this->demo, (int) today()->year, [], $this->usuario('admin@demo.test'));
        $v = $this->vehiculo();

        $this->actingAs($this->usuario('admin@demo.test'));
        foreach ([
            '/app/cootrasec-demo/programa-mantenimiento',
            "/app/cootrasec-demo/programa-mantenimiento/{$programa->id}",
            '/app/cootrasec-demo/calendario-mantenimiento',
            '/app/cootrasec-demo/plantillas-mantenimiento',
            '/app/cootrasec-demo/plantillas-mantenimiento/crear',
            '/app/cootrasec-demo/plantillas-mantenimiento/'.$this->general()->id.'/editar',
            "/app/cootrasec-demo/vehiculos/{$v->id}/plan-mantenimiento",
            '/app/cootrasec-demo/plan-preventivo',
            '/app/cootrasec-demo/integraciones',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_plan_del_vehiculo_desde_la_pantalla(): void
    {
        $this->entrarComo('admin@demo.test');
        $v = $this->vehiculo();
        $rutinas = PlanPreventivo::rutinasDe($v)->take(2)->map(fn ($r) => ['id' => null, 'nombre' => $r->nombre, 'intervalo_km' => 7000, 'intervalo_meses' => 4, 'descripcion' => null])->values()->all();

        Livewire::test(PlanMantenimientoVehiculo::class, ['record' => $v->getRouteKey()])
            ->set('plan.modo', 'personalizado')
            ->set('plan.rutinas', $rutinas)
            ->call('guardarPlan')
            ->assertHasNoErrors();

        $this->assertTrue($v->refresh()->plan_personalizado);
        $this->assertSame([7000, 7000], PlanPreventivo::rutinasDe($v)->pluck('intervalo_km')->all());

        Livewire::test(PlanMantenimientoVehiculo::class, ['record' => $v->getRouteKey()])->call('partidaHoy')->assertHasNoErrors();
        $this->assertSame(0, PlanPreventivo::para($v)->where('estado', PlanPreventivo::SIN_REGISTRO)->count());
    }

    public function test_webhooks_firmados_por_evento_y_avisos_del_programa(): void
    {
        Http::fake(['n8n.test/*' => Http::response(['ok' => true]), 'caido.test/*' => Http::response('caído', 500)]);
        $this->demo->forceFill([
            'webhook_activo' => true,
            'webhook_url' => 'https://n8n.test/webhook/ruta',
            'webhook_secreto' => 'secreto-de-prueba',
            'webhook_eventos' => ['plan_accion.creado', 'mantenimiento.actividad_proxima', 'mantenimiento.actividad_vencida'],
        ])->save();

        GestionarPlanAccion::crear($this->demo, $this->vehiculo(), ['titulo' => 'Luz de placa', 'prioridad' => 'baja']);

        $entrega = WebhookEntrega::query()->deEmpresa($this->demo)->latest('id')->firstOrFail();
        $this->assertSame('plan_accion.creado', $entrega->evento);
        $this->assertSame('enviado', $entrega->estado, 'La cola es síncrona en pruebas.');
        Http::assertSent(function ($request) {
            $firma = 'sha256='.hash_hmac('sha256', $request->body(), 'secreto-de-prueba');

            return $request->url() === 'https://n8n.test/webhook/ruta'
                && $request->header('X-Ruta-Evento')[0] === 'plan_accion.creado'
                && $request->header('X-Ruta-Firma')[0] === $firma
                && $request['datos']['placa'] === 'DMO102';
        });

        // Evento no elegido: no se envía.
        $antes = WebhookEntrega::query()->count();
        GestionarPlanAccion::cerrar(PlanAccion::query()->latest('id')->first(), 'ok');
        $this->assertSame($antes, WebhookEntrega::query()->count());

        // Avisos diarios del programa: una sola vez por actividad.
        $programa = ProgramaAnual::generar($this->demo, (int) today()->year);
        $a = $programa->actividades()->first();
        $a->forceFill(['fecha_programada' => today()->addDays(2)->toDateString()])->save();
        $r = AvisosPrograma::enviar($this->demo->refresh());
        $this->assertGreaterThanOrEqual(1, $r['proximas']);
        $this->assertSame(0, AvisosPrograma::enviar($this->demo)['proximas']);
        $this->assertTrue(WebhookEntrega::query()->where('evento', 'mantenimiento.actividad_proxima')->exists());

        // Webhook que falla: queda fallido con el error.
        $this->demo->forceFill(['webhook_url' => 'https://caido.test/webhook'])->save();
        $fallida = Webhooks::emitir($this->demo, 'prueba', []);
        $this->assertSame('fallido', $fallida->refresh()->estado);
        $this->assertSame(500, $fallida->codigo_respuesta);
    }
}
