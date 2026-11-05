<?php

namespace Tests\Feature\Mantenimiento;

use App\Actions\Mantenimiento\AnularMantenimiento;
use App\Actions\Mantenimiento\GestionarPlanAccion;
use App\Actions\Mantenimiento\PlanDeVehiculo;
use App\Actions\Mantenimiento\RegistrarMantenimiento;
use App\Actions\Mantenimiento\SeguimientoPreventivo;
use App\Actions\Pesv\RegistrarInspeccion;
use App\Enums\EstadoPlan;
use App\Enums\OrigenPlan;
use App\Enums\PrioridadPlan;
use App\Enums\TipoInspeccion;
use App\Filament\App\Resources\Mantenimientos\Pages\RegistrarMantenimiento as PaginaRegistrar;
use App\Filament\App\Resources\PlanesAccion\Pages\VerPlanAccion;
use App\Filament\App\Resources\Vehiculos\Pages\VerVehiculo;
use App\Filament\App\Resources\Vehiculos\RelationManagers\MantenimientosRelationManager;
use App\Models\Conductor;
use App\Models\Evidencia;
use App\Models\ItemChequeo;
use App\Models\Mantenimiento;
use App\Models\PlanAccion;
use App\Models\PlantillaMantenimiento;
use App\Models\RutinaMantenimiento;
use App\Models\Vehiculo;
use App\Support\Mantenimiento\PlanPreventivo;
use App\Support\Mantenimiento\RutinasBase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class MantenimientoTest extends ConfiguracionTestCase
{
    private function vehiculo(string $placa = 'DMO101'): Vehiculo
    {
        return Vehiculo::query()->deEmpresa($this->demo)->where('placa', $placa)->firstOrFail();
    }

    private function conductor(): Conductor
    {
        return Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->firstOrFail();
    }

    private function rutina(string $nombre = 'Aceite de motor y filtro de aceite'): RutinaMantenimiento
    {
        return RutinaMantenimiento::query()->deEmpresa($this->demo)->whereNotNull('plantilla_mantenimiento_id')->where('nombre', $nombre)->firstOrFail();
    }

    private function registrar(array $datos = [], array $archivos = [], ?Conductor $conductor = null, ?Vehiculo $vehiculo = null): Mantenimiento
    {
        return app(RegistrarMantenimiento::class)($this->demo, $vehiculo ?? $this->vehiculo(), [
            'fecha' => today()->toDateString(),
            'tipo' => 'preventivo',
            'accion' => 'cambio',
            'actividad' => 'Cambio de aceite 15W40 y filtro.',
            ...$datos,
        ], $archivos, $conductor?->usuario ?? $this->usuario('admin@demo.test'), $conductor);
    }

    private static function factura(string $nombre = 'factura.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 120, 'application/pdf');
    }

    public function test_pantallas_de_mantenimiento_y_permisos(): void
    {
        $vehiculo = $this->vehiculo();
        $mantenimiento = $this->registrar(['kilometraje' => 125500, 'rutinas' => [$this->rutina()->id]], [self::factura()]);
        $plan = PlanAccion::query()->deEmpresa($this->demo)->firstOrFail();

        $this->actingAs($this->usuario('admin@demo.test'));
        foreach ([
            '/app/cootrasec-demo/mantenimientos',
            '/app/cootrasec-demo/mantenimientos/registrar',
            "/app/cootrasec-demo/mantenimientos/registrar?vehiculo={$vehiculo->id}",
            "/app/cootrasec-demo/mantenimientos/{$mantenimiento->id}",
            '/app/cootrasec-demo/planes-accion',
            "/app/cootrasec-demo/planes-accion/{$plan->id}",
            '/app/cootrasec-demo/plantillas-mantenimiento',
            '/app/cootrasec-demo/plan-preventivo',
            "/app/cootrasec-demo/vehiculos/{$vehiculo->id}",
        ] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('evidencia', $mantenimiento->evidencias->first()->id))->assertOk();
    }

    public function test_gerencia_ve_pero_no_registra_y_otra_empresa_no_ve(): void
    {
        $mantenimiento = $this->registrar([], [self::factura()]);

        $this->actingAs($this->usuario('gerencia@demo.test'));
        $this->get('/app/cootrasec-demo/mantenimientos')->assertOk();
        $this->get('/app/cootrasec-demo/mantenimientos/registrar')->assertForbidden();
        $this->get(route('evidencia', $mantenimiento->evidencias->first()->id))->assertOk();

        $this->actingAs($this->usuario('admin@ejemplo.test'));
        $this->get(route('evidencia', $mantenimiento->evidencias->first()->id))->assertNotFound();
    }

    public function test_cada_empresa_recibe_las_rutinas_base_una_vez(): void
    {
        $this->assertSame(count(RutinasBase::RUTINAS), RutinaMantenimiento::query()->deEmpresa($this->otra)->count());
        $this->assertTrue(PlantillaMantenimiento::query()->deEmpresa($this->otra)->where('por_defecto', true)->exists());
        RutinasBase::crear($this->otra);
        $this->assertSame(count(RutinasBase::RUTINAS), RutinaMantenimiento::query()->deEmpresa($this->otra)->count());
    }

    public function test_plan_preventivo_por_kilometraje_y_tiempo(): void
    {
        $vehiculo = $this->vehiculo('DMO102');
        $aceite = $this->rutina();

        // Sin un mantenimiento previo de la rutina no hay punto de partida.
        $fila = PlanPreventivo::para($vehiculo)->firstWhere('rutina.id', $aceite->id);
        $this->assertSame(PlanPreventivo::SIN_REGISTRO, $fila['estado']);

        // Aceite a los 100.000 km → próximo a los 105.000 km o en 3 meses.
        $this->registrar(['kilometraje' => 100000, 'rutinas' => [$aceite->id]], vehiculo: $vehiculo);
        $fila = PlanPreventivo::para($vehiculo, 101000)->firstWhere('rutina.id', $aceite->id);
        $this->assertSame(105000, $fila['proximo_km']);
        $this->assertTrue($fila['proxima_fecha']->isSameDay(today()->addMonthsNoOverflow(3)));
        $this->assertSame(PlanPreventivo::AL_DIA, $fila['estado']);
        $this->assertSame(PlanPreventivo::PROXIMO, PlanPreventivo::para($vehiculo, 104200)->firstWhere('rutina.id', $aceite->id)['estado']);
        $this->assertSame(PlanPreventivo::VENCIDO, PlanPreventivo::para($vehiculo, 105000)->firstWhere('rutina.id', $aceite->id)['estado']);

        // Plan personalizado: cambia el intervalo; el mantenimiento hecho con la rutina de la plantilla cuenta.
        PlanDeVehiculo::personalizar($vehiculo, PlanPreventivo::rutinasDe($vehiculo)->map(fn ($r) => [
            'nombre' => $r->nombre, 'intervalo_km' => $r->nombre === $aceite->nombre ? 8000 : $r->intervalo_km, 'intervalo_meses' => $r->intervalo_meses,
        ])->all());
        $fila = PlanPreventivo::para($vehiculo->refresh(), 101000)->firstWhere('rutina.nombre', $aceite->nombre);
        $this->assertSame(108000, $fila['proximo_km'], 'El mantenimiento hecho con la rutina general cuenta.');
        $this->assertSame(1, PlanPreventivo::rutinasDe($vehiculo)->where('nombre', $aceite->nombre)->count());
    }

    public function test_rutina_vencida_abre_plan_y_el_mantenimiento_lo_cierra(): void
    {
        $vehiculo = $this->vehiculo('DMO102');
        $aceite = $this->rutina();
        $this->registrar(['kilometraje' => 100000, 'rutinas' => [$aceite->id], 'fecha' => today()->subMonths(4)->toDateString()], vehiculo: $vehiculo);

        $plan = PlanAccion::query()->deEmpresa($this->demo)->where('vehiculo_id', $vehiculo->id)->where('origen', OrigenPlan::Preventivo->value)->firstOrFail();
        $this->assertSame(PrioridadPlan::Media, $plan->prioridad, 'Solo alerta: no bloquea.');
        $this->assertSame(0, SeguimientoPreventivo::revisar($vehiculo), 'No duplica el plan pendiente.');

        $this->registrar(['kilometraje' => 104000, 'rutinas' => [$aceite->id]], vehiculo: $vehiculo);
        $this->assertSame(EstadoPlan::Cerrado, $plan->refresh()->estado);
        $this->artisan('ruta:mantenimiento')->assertSuccessful();
    }

    public function test_falla_del_preoperacional_abre_plan_reincidencia_y_cierre_con_evidencia(): void
    {
        $conductor = $this->conductor();
        $critico = ItemChequeo::query()->deEmpresa($this->demo)->deTipo(TipoInspeccion::Preoperacional)->where('critico', true)->firstOrFail();
        $respuestas = fn (array $cambios = []) => array_replace(
            ItemChequeo::query()->deEmpresa($this->demo)->deTipo(TipoInspeccion::Preoperacional)->activos()->pluck('id')->mapWithKeys(fn ($id) => [$id => ['respuesta' => 'si']])->all(),
            $cambios,
        );

        $falla = app(RegistrarInspeccion::class)($this->demo, TipoInspeccion::Preoperacional, $this->vehiculo(),
            $respuestas([$critico->id => ['respuesta' => 'no', 'observacion' => 'Fuga']]), conductor: $conductor, kilometraje: 126000, usuario: $conductor->usuario);
        $this->assertFalse($falla->apta());

        $plan = PlanAccion::query()->deEmpresa($this->demo)->where('vehiculo_id', $this->vehiculo()->id)->pendientes()->firstOrFail();
        $this->assertSame(PrioridadPlan::Alta, $plan->prioridad);
        $this->assertSame($critico->descripcion, $plan->hallazgos[0]['descripcion']);

        // Vuelve a fallar: reincidencia en el mismo plan.
        app(RegistrarInspeccion::class)($this->demo, TipoInspeccion::Preoperacional, $this->vehiculo(),
            $respuestas([$critico->id => ['respuesta' => 'no', 'observacion' => 'Sigue la fuga']]), conductor: $conductor, kilometraje: 126010, usuario: $conductor->usuario);
        $plan->refresh();
        $this->assertSame(1, $plan->reincidencias);
        $this->assertSame(2, $plan->hallazgos[0]['veces']);
        $this->assertSame(1, PlanAccion::query()->deEmpresa($this->demo)->where('vehiculo_id', $this->vehiculo()->id)->pendientes()->count());

        // El conductor registra el mantenimiento con evidencia desde el portal → por verificar.
        $this->registrar(['tipo' => 'correctivo', 'accion' => 'reparacion', 'actividad' => 'Cambio de manguera', 'plan_accion_id' => $plan->id], [UploadedFile::fake()->image('foto.jpg')], $conductor);
        $this->assertSame(EstadoPlan::PorVerificar, $plan->refresh()->estado);
        $this->assertTrue($this->usuario('admin@demo.test')->notifications()->where('data', 'like', '%por verificar%')->exists());

        // Sigue NO APTO hasta que la empresa cierre.
        $this->assertFalse(app(RegistrarInspeccion::class)($this->demo, TipoInspeccion::Preoperacional, $this->vehiculo(), $respuestas(), conductor: $conductor, kilometraje: 126020, usuario: $conductor->usuario)->apta());

        // La empresa rechaza, luego cierra.
        $this->entrarComo('admin@demo.test');
        Livewire::test(VerPlanAccion::class, ['record' => $plan->getRouteKey()])
            ->callAction('rechazar', ['motivo' => 'La foto no se ve'])
            ->assertHasNoActionErrors();
        $this->assertSame(EstadoPlan::Rechazado, $plan->refresh()->estado);

        Livewire::test(VerPlanAccion::class, ['record' => $plan->getRouteKey()])
            ->callAction('cerrar', ['observacion' => 'Verificado en patio'])
            ->assertHasNoActionErrors();
        $this->assertSame(EstadoPlan::Cerrado, $plan->refresh()->estado);
        $this->assertTrue(app(RegistrarInspeccion::class)($this->demo, TipoInspeccion::Preoperacional, $this->vehiculo(), $respuestas(), conductor: $conductor, kilometraje: 126030, usuario: $conductor->usuario)->apta());
    }

    public function test_validaciones_al_registrar_y_anular(): void
    {
        // El conductor debe adjuntar evidencia; rutinas exigen kilometraje; archivos no válidos se rechazan.
        try {
            $this->registrar([], [], $this->conductor());
            $this->fail('Debió rechazar');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('archivos', $e->errors());
        }
        try {
            $this->registrar(['rutinas' => [$this->rutina()->id], 'fecha' => today()->addDay()->toDateString()], [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]);
            $this->fail('Debió rechazar');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kilometraje', $e->errors());
            $this->assertArrayHasKey('fecha', $e->errors());
            $this->assertArrayHasKey('archivos', $e->errors());
        }

        $m = $this->registrar(['kilometraje' => 130000, 'rutinas' => [$this->rutina()->id]]);
        app(AnularMantenimiento::class)($m, 'Error de digitación', $this->usuario('admin@demo.test'));
        $this->assertTrue($m->refresh()->anulado());
        $this->assertSame(PlanPreventivo::SIN_REGISTRO, PlanPreventivo::para($this->vehiculo())->firstWhere('rutina.id', $this->rutina()->id)['estado'], 'Anulado no cuenta.');
    }

    public function test_registrar_desde_la_pantalla_con_evidencias(): void
    {
        $this->entrarComo('admin@demo.test');
        $vehiculo = $this->vehiculo('DMO102');

        Livewire::withQueryParams(['vehiculo' => $vehiculo->id])
            ->test(PaginaRegistrar::class)
            ->assertSchemaStateSet(['vehiculo_id' => $vehiculo->id])
            ->fillForm([
                'tipo' => 'preventivo',
                'accion' => 'cambio',
                'kilometraje' => 90000,
                'actividad' => 'Cambio de filtros',
                'rutinas' => [$this->rutina('Filtro de aire')->id],
                'valor' => 120000,
                'archivos' => [self::factura()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $m = Mantenimiento::query()->deEmpresa($this->demo)->latest('id')->firstOrFail();
        $this->assertSame(90000, $m->kilometraje);
        $this->assertCount(1, $m->evidencias);
        Storage::disk(Evidencia::DISCO)->assertExists($m->evidencias->first()->path);
        $this->assertSame([$this->rutina('Filtro de aire')->id], $m->rutinas->pluck('id')->all());
    }

    public function test_plan_manual_y_cierre_exige_soporte(): void
    {
        $this->entrarComo('admin@demo.test');
        $plan = GestionarPlanAccion::crear($this->demo, $this->vehiculo(), ['titulo' => 'Control vial: luz de placa dañada', 'prioridad' => 'baja'], $this->usuario('admin@demo.test'));
        $this->assertSame(OrigenPlan::Manual, $plan->origen);

        $this->assertThrows(fn () => GestionarPlanAccion::cerrar($plan, null), ValidationException::class);
        GestionarPlanAccion::adjuntarEvidencia($plan, [UploadedFile::fake()->image('luz.png')], 'Cambiada', $this->usuario('admin@demo.test'));
        $this->assertSame(EstadoPlan::PorVerificar, $plan->refresh()->estado);
        GestionarPlanAccion::cerrar($plan, null);
        $this->assertSame(EstadoPlan::Cerrado, $plan->refresh()->estado);
        $this->assertThrows(fn () => GestionarPlanAccion::iniciar($plan), ValidationException::class);
    }

    public function test_el_conductor_registra_el_arreglo_desde_el_portal(): void
    {
        $vehiculo = $this->vehiculo();
        $plan = GestionarPlanAccion::crear($this->demo, $vehiculo, ['titulo' => 'Espejo roto', 'prioridad' => 'media']);

        $this->post('/conductor/ingresar', ['documento' => '20000001', 'clave' => 'Ruta2026*']);
        $this->get('/conductor/mantenimiento')->assertOk()->assertSee('Espejo roto')->assertSee('Registrar arreglo con evidencia');
        $this->get(route('conductor.mantenimiento.registrar', ['vehiculo' => $vehiculo->id, 'plan' => $plan->id]))->assertOk()->assertSee($plan->numero);

        // Sin evidencia no se registra.
        $this->post(route('conductor.mantenimiento.guardar', $vehiculo), [
            'tipo' => 'correctivo', 'accion' => 'cambio', 'fecha' => today()->toDateString(), 'actividad' => 'Cambio de espejo', 'plan_accion_id' => $plan->id,
        ])->assertSessionHasErrors('archivos');

        $this->post(route('conductor.mantenimiento.guardar', $vehiculo), [
            'tipo' => 'correctivo', 'accion' => 'cambio', 'fecha' => today()->toDateString(), 'actividad' => 'Cambio de espejo', 'plan_accion_id' => $plan->id,
            'archivos' => [UploadedFile::fake()->image('espejo.jpg'), self::factura()],
        ])->assertRedirect(route('conductor.mantenimiento'))->assertSessionHas('aviso');

        $m = Mantenimiento::query()->deEmpresa($this->demo)->latest('id')->firstOrFail();
        $this->assertSame($this->conductor()->id, $m->conductor_id);
        $this->assertCount(2, $m->evidencias);
        $this->assertSame(EstadoPlan::PorVerificar, $plan->refresh()->estado);
        $this->get(route('evidencia', $m->evidencias->first()->id))->assertOk();
        $this->get('/conductor/mantenimiento')->assertSee('La empresa lo está revisando');

        // Un vehículo que no es suyo: no.
        $ajeno = $this->vehiculo('DMO103');
        $this->get(route('conductor.mantenimiento.registrar', $ajeno))->assertNotFound();
    }

    public function test_hoja_de_vida_muestra_plan_preventivo_y_mantenimientos(): void
    {
        $this->entrarComo('admin@demo.test');
        $vehiculo = $this->vehiculo('DMO102');
        $this->registrar(['kilometraje' => 90000, 'rutinas' => [$this->rutina()->id], 'actividad' => 'Cambio de aceite sintético'], vehiculo: $vehiculo);

        Livewire::test(MantenimientosRelationManager::class, [
            'ownerRecord' => $vehiculo,
            'pageClass' => VerVehiculo::class,
        ])->assertSee('Plan preventivo')->assertSee('95.000 km')->assertSee('Cambio de aceite sintético');
    }
}
