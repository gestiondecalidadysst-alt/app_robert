<?php

namespace Tests\Feature\Operacion;

use App\Actions\Operacion\AnularFuec;
use App\Actions\Operacion\ExpedirFuec;
use App\Actions\Operacion\PlanificarServicio;
use App\Enums\EstadoServicio;
use App\Filament\App\Pages\Planeador;
use App\Filament\App\Resources\Fuecs\Pages\ExpedirFuec as PaginaExpedirFuec;
use App\Filament\App\Resources\Servicios\Pages\CrearServicio;
use App\Models\Conductor;
use App\Models\Contrato;
use App\Models\Fuec;
use App\Models\Municipio;
use App\Models\Ruta;
use App\Models\Servicio;
use App\Models\Vehiculo;
use App\Models\WebhookEntrega;
use App\Support\Operacion\Jornada;
use App\Support\Pdf\PdfPlanViaje;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class PlaneadorTest extends ConfiguracionTestCase
{
    private function monteria(): int
    {
        return Municipio::firstWhere('codigo_dane', '23001')->id;
    }

    private function cerete(): int
    {
        return Municipio::where('nombre', 'like', 'CERET%')->value('id');
    }

    private function vehiculo(string $placa = 'DMO101'): Vehiculo
    {
        return Vehiculo::query()->deEmpresa($this->demo)->where('placa', $placa)->firstOrFail();
    }

    private function conductor(string $doc = '20000001'): Conductor
    {
        return Conductor::query()->deEmpresa($this->demo)->where('documento', $doc)->firstOrFail();
    }

    private function planificar(array $datos = []): Servicio
    {
        return app(PlanificarServicio::class)->guardar($this->demo, [
            'fecha_inicio' => today()->addDays(3)->toDateString(),
            'origen_municipio_id' => $this->monteria(),
            'destino_municipio_id' => $this->cerete(),
            ...$datos,
        ]);
    }

    public function test_todo_extracto_aparece_en_el_planeador_con_su_rutograma(): void
    {
        $this->entrarComo('admin@demo.test');
        $fuec = Fuec::query()->deEmpresa($this->demo)->firstOrFail();
        $servicio = Servicio::query()->where('fuec_id', $fuec->id)->firstOrFail();

        $this->assertSame(EstadoServicio::ConExtracto, $servicio->estado);
        $this->assertSame($fuec->vehiculo_id, $servicio->vehiculo_id);
        $this->assertSame($fuec->conductores->pluck('id')->all(), $servicio->conductores->pluck('id')->all());
        $this->assertNotNull($servicio->ruta_id, 'Se asigna el rutograma por origen y destino.');

        // El planificado demo va Cereté → Montería: toma el mismo rutograma (ida y vuelta).
        $planificado = Servicio::query()->whereNull('fuec_id')->firstOrFail();
        $this->assertSame($servicio->ruta_id, $planificado->ruta_id);

        // Sin ida y vuelta, el inverso no aplica.
        Ruta::query()->update(['ida_y_vuelta' => false]);
        $this->assertNull(Ruta::sugerida($this->demo->id, $this->cerete(), $this->monteria()));
        $this->assertNotNull(Ruta::sugerida($this->demo->id, $this->monteria(), $this->cerete()));
    }

    public function test_plan_libre_y_generar_extracto_desde_el_servicio(): void
    {
        $this->entrarComo('admin@demo.test');

        // Libre: sin contrato, vehículo ni conductor.
        $servicio = $this->planificar(['hora_salida' => '07:00', 'hora_llegada' => '08:00']);
        $this->assertSame(EstadoServicio::Planificado, $servicio->estado);
        $this->assertSame('07:00:00', $servicio->hora_salida);
        $this->assertThrows(fn () => $this->planificar(['fecha_fin' => today()->toDateString()]), ValidationException::class);

        // Se completa y se genera el extracto: queda enlazado, sin crear otro servicio.
        $contrato = Contrato::query()->deEmpresa($this->demo)->where('numero', 2)->firstOrFail();
        app(PlanificarServicio::class)->guardar($this->demo, [
            'contrato_id' => $contrato->id,
            'fecha_inicio' => today()->addDays(3)->toDateString(),
            'hora_salida' => '16:00',
            'hora_llegada' => '18:00',
            'origen_municipio_id' => $this->monteria(),
            'destino_municipio_id' => $this->cerete(),
            'vehiculo_id' => $this->vehiculo()->id,
            'conductores' => [$this->conductor()->id],
        ], $servicio);

        $antes = Servicio::count();
        $fuec = app(ExpedirFuec::class)($this->demo, [
            'contrato_id' => $contrato->id,
            'vehiculo_id' => $this->vehiculo()->id,
            'conductores' => [$this->conductor()->id],
            'fecha_inicio' => today()->addDays(3)->toDateString(),
            'fecha_fin' => today()->addDays(3)->toDateString(),
            'hora_inicio' => '16:00',
            'hora_fin' => '18:00',
            'origen_municipio_id' => $this->monteria(),
            'destino_municipio_id' => $this->cerete(),
            'servicio_id' => $servicio->id,
        ]);

        $this->assertSame($antes, Servicio::count());
        $servicio->refresh();
        $this->assertSame($fuec->id, $servicio->fuec_id);
        $this->assertSame(EstadoServicio::ConExtracto, $servicio->estado);

        // Con extracto, solo cambian ruta, conducción, pasajeros y observaciones.
        app(PlanificarServicio::class)->guardar($this->demo, ['pasajeros' => 12, 'fecha_inicio' => today()->addDays(9)->toDateString()], $servicio);
        $this->assertSame(12, $servicio->refresh()->pasajeros);
        $this->assertSame(today()->addDays(3)->toDateString(), $servicio->fecha_inicio->toDateString());

        // No se cancela con extracto vigente; al anular el FUEC se cancela solo.
        $this->assertThrows(fn () => app(PlanificarServicio::class)->cancelar($servicio, 'Cliente canceló'), ValidationException::class);
        app(AnularFuec::class)($fuec, 'Cliente canceló');
        $this->assertSame(EstadoServicio::Cancelado, $servicio->refresh()->estado);
    }

    public function test_avisos_de_jornada_no_bloquean(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->demo->update(['jornada_max_horas_dia' => 3, 'jornada_max_horas_continuas' => 2, 'jornada_descanso_horas' => 10]);
        $c = $this->conductor('20000002');
        $dia = today()->addDays(5);

        $a = $this->planificar(['fecha_inicio' => $dia->toDateString(), 'hora_salida' => '05:00', 'hora_llegada' => '07:30', 'conductores' => [$c->id]]);
        $b = $this->planificar(['fecha_inicio' => $dia->toDateString(), 'hora_salida' => '19:00', 'hora_llegada' => '22:00', 'conductores' => [$c->id], 'ruta_id' => null]);
        $sig = $this->planificar(['fecha_inicio' => $dia->copy()->addDay()->toDateString(), 'hora_salida' => '04:00', 'hora_llegada' => '05:00', 'conductores' => [$c->id]]);

        $avisos = implode(' ', Jornada::avisos($b->refresh(), [$c->id]));
        $this->assertStringContainsString('Conducción continua estimada de 3 h', $avisos, 'Sin rutograma (sin paradas) y más de 2 h.');
        $this->assertStringContainsString('supera las 3 h de conducción al día', $avisos);

        $avisos = implode(' ', Jornada::avisos($sig->refresh(), [$c->id]));
        $this->assertStringContainsString('descansa menos de 10 h', $avisos);

        // Cruce con otro servicio planificado: aviso.
        $cruce = $this->planificar(['fecha_inicio' => $dia->toDateString(), 'hora_salida' => '06:00', 'hora_llegada' => '07:00', 'conductores' => [$c->id]]);
        $this->assertStringContainsString('ya tiene el servicio', implode(' ', Jornada::avisos($cruce->refresh(), [$c->id])));
        $this->assertSame(4, Servicio::query()->whereIn('id', [$a->id, $b->id, $sig->id, $cruce->id])->count(), 'Los avisos no impiden guardar.');

        // Tablero por conductor: horas del día.
        $min = Jornada::minutosPorConductor(Servicio::with(['conductores', 'ruta'])->get(), $dia, $dia);
        $this->assertGreaterThan(180, $min[$c->id][$dia->toDateString()]);
    }

    public function test_pantallas_del_planeador(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->actingAs($this->usuario('admin@demo.test'));
        $servicio = Servicio::query()->whereNull('fuec_id')->firstOrFail();
        $ruta = Ruta::query()->firstOrFail();

        foreach ([
            '/app/cootrasec-demo/planeador',
            '/app/cootrasec-demo/planeador?modo=conductor',
            '/app/cootrasec-demo/servicios',
            '/app/cootrasec-demo/servicios/planificar?fecha=' . today()->toDateString(),
            "/app/cootrasec-demo/servicios/{$servicio->id}",
            "/app/cootrasec-demo/servicios/{$servicio->id}/editar",
            '/app/cootrasec-demo/rutogramas',
            '/app/cootrasec-demo/rutogramas/crear',
            "/app/cootrasec-demo/rutogramas/{$ruta->id}/editar",
            "/app/cootrasec-demo/fuec/expedir?servicio={$servicio->id}",
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/app/cootrasec-demo/planeador')->assertSee('DMO101')->assertSee('Planeador de servicios');
        $this->assertStringStartsWith('%PDF', PdfPlanViaje::servicio($servicio));
        $this->assertStringStartsWith('%PDF', PdfPlanViaje::rutograma($ruta));
    }

    public function test_gerencia_ve_el_tablero_pero_no_planifica(): void
    {
        $this->actingAs($this->usuario('gerencia@demo.test'));
        $this->get('/app/cootrasec-demo/planeador')->assertOk()->assertDontSee('Planificar servicio');
        $this->get('/app/cootrasec-demo/servicios/planificar')->assertForbidden();
    }

    public function test_formularios_planificar_y_expedir_desde_el_servicio(): void
    {
        $this->entrarComo('admin@demo.test');

        Livewire::test(CrearServicio::class)
            ->fillForm([
                'fecha_inicio' => today()->addDays(4)->toDateString(),
                'hora_salida' => '08:00',
                'origen_municipio_id' => $this->monteria(),
                'destino_municipio_id' => $this->cerete(),
                'vehiculo_id' => $this->vehiculo('DMO102')->id,
                'conductor_1' => $this->conductor('20000002')->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = Servicio::query()->latest('id')->firstOrFail();
        $this->assertSame([$this->conductor('20000002')->id], $nuevo->conductores->pluck('id')->all());

        Livewire::withQueryParams(['servicio' => $nuevo->id])->test(PaginaExpedirFuec::class)
            ->assertSet('servicioId', $nuevo->id)
            ->assertSchemaStateSet(['vehiculo_id' => $nuevo->vehiculo_id, 'conductor_1' => $this->conductor('20000002')->id, 'hora_inicio' => '08:00']);

        Livewire::test(Planeador::class)->call('cambiarSemana', 1)->set('modo', 'conductor')->assertOk();
    }

    public function test_portal_plan_leido_y_seguimiento_opcional(): void
    {
        Http::fake();
        $this->demo->update(['webhook_activo' => true, 'webhook_url' => 'https://n8n.test/webhook', 'webhook_eventos' => ['servicio.novedad']]);
        $servicio = Servicio::query()->whereNotNull('fuec_id')->firstOrFail();

        $this->post('/conductor/ingresar', ['documento' => '20.000.001', 'clave' => 'Ruta2026*'])->assertRedirect(route('conductor.inicio'));
        $this->get(route('conductor.inicio'))->assertOk()->assertSee('Plan de viaje y rutograma');
        $this->get(route('conductor.servicio', $servicio))->assertOk()->assertSee('Leí el plan de viaje')->assertSee('Zona escolar');
        $this->get(route('conductor.servicio.pdf', $servicio))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $url = route('conductor.servicio.reportar', $servicio);
        $this->post($url, ['tipo' => 'leido'])->assertRedirect();
        $this->post($url, ['tipo' => 'inicio', 'kilometraje' => 1000, 'latitud' => 8.75, 'longitud' => -75.88])->assertRedirect();
        $this->post($url, ['tipo' => 'novedad', 'nota' => ''])->assertSessionHasErrors();
        $this->post($url, ['tipo' => 'novedad', 'nota' => 'Vía cerrada por derrumbe, tomo la alterna.'])->assertRedirect();
        $this->post($url, ['tipo' => 'fin', 'kilometraje' => 900])->assertSessionHasErrors('kilometraje');
        $this->post($url, ['tipo' => 'fin', 'kilometraje' => 1018])->assertRedirect();

        $servicio->refresh();
        $this->assertNotNull($servicio->leido_at);
        $this->assertSame(EstadoServicio::Finalizado, $servicio->estado);
        $this->assertSame([1000, 1018], [$servicio->km_inicial, $servicio->km_final]);
        $this->assertSame(['leido', 'inicio', 'novedad', 'fin'], $servicio->eventos()->pluck('tipo')->all());
        $this->assertEqualsWithDelta(8.75, $servicio->eventos()->where('tipo', 'inicio')->value('latitud'), 0.001);
        $this->assertSame(1, WebhookEntrega::query()->where('evento', 'servicio.novedad')->count());

        // Un servicio que no es suyo: 404.
        $ajeno = $this->planificarComo();
        $this->get(route('conductor.servicio', $ajeno))->assertNotFound();
    }

    private function planificarComo(): Servicio
    {
        $s = new Servicio([
            'fecha_inicio' => today(), 'fecha_fin' => today(),
            'origen_municipio_id' => $this->monteria(), 'destino_municipio_id' => $this->cerete(),
            'estado' => EstadoServicio::Planificado,
        ]);
        $s->empresa_id = $this->demo->id;
        $s->save();

        return $s;
    }
}
