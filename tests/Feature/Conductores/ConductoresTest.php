<?php

namespace Tests\Feature\Conductores;

use App\Actions\Conductores\AsignarVehiculo;
use App\Actions\Conductores\CambiarEstadoConductor;
use App\Actions\Flota\CambiarEstadoVehiculo;
use App\Enums\EstadoConductor;
use App\Enums\EstadoDocumento;
use App\Enums\TipoDocumentoConductor;
use App\Enums\TipoNovedadConductor;
use App\Enums\TipoNovedadVehiculo;
use App\Filament\App\Resources\Conductores\Pages\CrearConductor;
use App\Filament\App\Resources\Conductores\Pages\VerConductor;
use App\Filament\App\Resources\Conductores\RelationManagers\VehiculosRelationManager;
use App\Filament\App\Resources\VencimientosConductores\Pages\ListarVencimientosConductores;
use App\Models\AsignacionVehiculo;
use App\Models\Conductor;
use App\Models\DocumentoConductor;
use App\Models\Vehiculo;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class ConductoresTest extends ConfiguracionTestCase
{
    private function conductor(string $documento): Conductor
    {
        return Conductor::query()->deEmpresa($this->demo)->where('documento', $documento)->firstOrFail();
    }

    private function vehiculo(string $placa): Vehiculo
    {
        return Vehiculo::query()->deEmpresa($this->demo)->where('placa', $placa)->firstOrFail();
    }

    public function test_el_administrador_abre_las_pantallas_de_conductores(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));
        $c = $this->conductor('20000003');
        $v = $this->vehiculo('DMO101');

        foreach ([
            '/app/cootrasec-demo/conductores',
            '/app/cootrasec-demo/conductores/crear',
            "/app/cootrasec-demo/conductores/{$c->id}",
            "/app/cootrasec-demo/conductores/{$c->id}/editar",
            '/app/cootrasec-demo/vencimientos-conductores',
            "/app/cootrasec-demo/vehiculos/{$v->id}",
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get("/app/cootrasec-demo/conductores/{$c->id}")
            ->assertSee('Conductor Tres Demo')
            ->assertSee('No puede operar')
            ->assertSee('Comparendos');
    }

    public function test_permisos_por_rol_en_conductores(): void
    {
        $c = $this->conductor('20000001');

        $this->actingAs($this->usuario('gerencia@demo.test'));
        $this->get("/app/cootrasec-demo/conductores/{$c->id}")->assertOk();
        $this->get('/app/cootrasec-demo/conductores/crear')->assertForbidden();
        $this->get("/app/cootrasec-demo/conductores/{$c->id}/editar")->assertForbidden();
    }

    public function test_no_se_ven_conductores_de_otra_empresa(): void
    {
        $c = $this->conductor('20000001');

        $this->actingAs($this->usuario('admin@ejemplo.test'));
        $this->get("/app/transportes-ejemplo/conductores/{$c->id}")->assertNotFound();

        $this->entrarComo('admin@ejemplo.test', $this->otra);
        $this->assertSame(0, Conductor::count());
        $this->assertSame(0, DocumentoConductor::count());
        $this->assertSame(0, AsignacionVehiculo::count());
    }

    public function test_crear_conductor_y_documento_unico_por_empresa(): void
    {
        $this->entrarComo('admin@demo.test');

        Livewire::test(CrearConductor::class)
            ->fillForm(['documento' => '1.234.567', 'nombres' => 'Nuevo', 'apellidos' => 'Conductor'])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = Conductor::firstWhere('documento', '1234567');
        $this->assertNotNull($nuevo);
        $this->assertSame(EstadoConductor::Activo, $nuevo->estado);
        $this->assertSame(['Licencia de conducción: sin registrar.', 'Seguridad social (planilla): sin registrar.'], $nuevo->bloqueosOperacion());

        Livewire::test(CrearConductor::class)
            ->fillForm(['documento' => '20000001', 'nombres' => 'Repetido', 'apellidos' => 'X'])
            ->call('create')
            ->assertHasFormErrors(['documento']);
    }

    public function test_bloqueos_del_conductor(): void
    {
        $alDia = $this->conductor('20000001')->load('documentos.categoriaLicencia');
        $this->assertTrue($alDia->puedeOperar());

        // Seguridad social vencida; al pagar la planilla del mes se habilita y el pago anterior queda en el historial.
        $sinPlanilla = $this->conductor('20000003')->load('documentos');
        $this->assertSame(EstadoDocumento::Vencido, $sinPlanilla->estadoDocumental());
        $sinPlanilla->documentos()->create([
            'tipo' => TipoDocumentoConductor::SeguridadSocial,
            'fecha_expedicion' => today(),
            'fecha_vencimiento' => today()->addMonth(),
        ]);
        $this->assertTrue($sinPlanilla->refresh()->load('documentos.categoriaLicencia')->puedeOperar());
        $this->assertSame(2, $sinPlanilla->documentos()->where('tipo', TipoDocumentoConductor::SeguridadSocial)->count());

        // Licencia de servicio particular (B1).
        $particular = $this->conductor('20000004')->load('documentos.categoriaLicencia');
        $this->assertStringContainsString('categoría B1', implode(' ', $particular->bloqueosOperacion()));

        // Suspendido.
        $this->assertContains('El conductor está suspendido.', $this->conductor('20000005')->load('documentos')->bloqueosOperacion());
    }

    public function test_los_dias_de_aviso_dependen_del_tipo_de_documento(): void
    {
        $c = $this->conductor('20000001');

        $planilla = $c->documentos()->create(['tipo' => TipoDocumentoConductor::SeguridadSocial, 'fecha_vencimiento' => today()->addDays(10)]);
        $licencia = $c->documentos()->create(['tipo' => TipoDocumentoConductor::Licencia, 'fecha_vencimiento' => today()->addDays(10)]);

        $this->assertSame(EstadoDocumento::Vigente, $planilla->estado(), 'La planilla mensual avisa 5 días antes.');
        $this->assertSame(EstadoDocumento::PorVencer, $licencia->estado(), 'La licencia avisa 30 días antes.');

        $porVencer = DocumentoConductor::query()->deEmpresa($this->demo)->porVencer()->pluck('id');
        $this->assertTrue($porVencer->contains($licencia->id));
        $this->assertFalse($porVencer->contains($planilla->id));
    }

    public function test_el_retiro_termina_las_asignaciones_y_se_puede_reintegrar(): void
    {
        $this->entrarComo('admin@demo.test');
        $c = $this->conductor('20000001');
        $this->assertSame(1, $c->asignacionesVigentes()->count());

        Livewire::test(VerConductor::class, ['record' => $c->getKey()])
            ->callAction('cambiarEstado', [
                'tipo' => TipoNovedadConductor::Retiro->value,
                'fecha' => today()->toDateString(),
                'observacion' => 'Renuncia voluntaria',
            ])
            ->assertHasNoActionErrors();

        $c->refresh();
        $this->assertSame(EstadoConductor::Retirado, $c->estado);
        $this->assertSame(0, $c->asignacionesVigentes()->count());
        $this->assertSame(1, $c->asignaciones()->count(), 'La asignación terminada queda en el historial.');

        // Un retirado no se asigna; tampoco se puede "suspender".
        $this->assertThrows(fn () => app(AsignarVehiculo::class)($c, $this->vehiculo('DMO102')), ValidationException::class);
        $this->assertThrows(fn () => app(CambiarEstadoConductor::class)($c, TipoNovedadConductor::Suspension, 'x'), ValidationException::class);

        app(CambiarEstadoConductor::class)($c, TipoNovedadConductor::Reintegro, 'Vuelve a la empresa');
        $this->assertSame(EstadoConductor::Activo, $c->refresh()->estado);
    }

    public function test_asignaciones_de_vehiculo(): void
    {
        $this->entrarComo('admin@demo.test');
        $c = $this->conductor('20000002');
        $v = $this->vehiculo('DMO101'); // ya tiene principal a "Conductor Uno Demo"

        Livewire::test(VehiculosRelationManager::class, ['ownerRecord' => $c, 'pageClass' => VerConductor::class])
            ->callAction(TestAction::make('create')->table(), [
                'vehiculo_id' => $v->id,
                'fecha_desde' => today()->toDateString(),
                'principal' => true,
            ])
            ->assertHasNoFormErrors();

        $principales = AsignacionVehiculo::query()->where('vehiculo_id', $v->id)->vigentes()->where('principal', true)->pluck('conductor_id');
        $this->assertEquals([$c->id], $principales->all(), 'Un vehículo tiene un solo conductor principal.');

        // No se repite la misma asignación vigente.
        $this->assertThrows(fn () => app(AsignarVehiculo::class)($c, $v), ValidationException::class);

        // No se asigna un vehículo desvinculado.
        $otro = $this->vehiculo('DMO106');
        app(CambiarEstadoVehiculo::class)($otro, TipoNovedadVehiculo::Desvinculacion, 'Sale');
        $this->assertThrows(fn () => app(AsignarVehiculo::class)($c, $otro->refresh()), ValidationException::class);

        // Terminar.
        $asignacion = AsignacionVehiculo::query()->where('conductor_id', $c->id)->where('vehiculo_id', $v->id)->vigentes()->firstOrFail();
        app(AsignarVehiculo::class)->terminar($asignacion);
        $this->assertFalse($asignacion->refresh()->vigente());
    }

    public function test_vencimientos_de_conductores_excluye_retirados(): void
    {
        $this->entrarComo('admin@demo.test');
        $c = $this->conductor('20000003');
        $vencida = DocumentoConductor::query()->vigentes()->where('conductor_id', $c->id)->vencidos()->get();
        $this->assertCount(1, $vencida);

        Livewire::test(ListarVencimientosConductores::class)->assertCanSeeTableRecords($vencida);

        app(CambiarEstadoConductor::class)($c, TipoNovedadConductor::Retiro, 'Retiro');

        Livewire::test(ListarVencimientosConductores::class)->assertCanNotSeeTableRecords($vencida);
    }
}
