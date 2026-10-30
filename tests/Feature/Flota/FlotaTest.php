<?php

namespace Tests\Feature\Flota;

use App\Actions\Flota\CambiarEstadoVehiculo;
use App\Enums\EstadoDocumento;
use App\Enums\EstadoVehiculo;
use App\Enums\TipoDocumentoVehiculo;
use App\Enums\TipoNovedadVehiculo;
use App\Filament\App\Resources\Vehiculos\Pages\CrearVehiculo;
use App\Filament\App\Resources\Vehiculos\Pages\VerVehiculo;
use App\Filament\App\Resources\Vehiculos\RelationManagers\DocumentosRelationManager;
use App\Filament\App\Resources\Vencimientos\Pages\ListarVencimientos;
use App\Models\ClaseVehiculo;
use App\Models\DocumentoVehiculo;
use App\Models\Marca;
use App\Models\Propietario;
use App\Models\Rol;
use App\Models\Vehiculo;
use Database\Seeders\PermisosSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class FlotaTest extends ConfiguracionTestCase
{
    private function vehiculo(string $placa): Vehiculo
    {
        return Vehiculo::query()->deEmpresa($this->demo)->where('placa', $placa)->firstOrFail();
    }

    public function test_el_administrador_abre_las_pantallas_de_flota(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));
        $v = $this->vehiculo('DMO103');

        foreach ([
            '/app/cootrasec-demo/vehiculos',
            '/app/cootrasec-demo/vehiculos/crear',
            "/app/cootrasec-demo/vehiculos/{$v->id}",
            "/app/cootrasec-demo/vehiculos/{$v->id}/editar",
            '/app/cootrasec-demo/propietarios',
            '/app/cootrasec-demo/vencimientos',
            '/app/cootrasec-demo',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get("/app/cootrasec-demo/vehiculos/{$v->id}")->assertSee('DMO103')->assertSee('No puede operar');
    }

    public function test_permisos_por_rol_en_flota(): void
    {
        $v = $this->vehiculo('DMO101');

        $this->actingAs($this->usuario('gerencia@demo.test'));
        $this->get('/app/cootrasec-demo/vehiculos')->assertOk();
        $this->get("/app/cootrasec-demo/vehiculos/{$v->id}")->assertOk();
        $this->get('/app/cootrasec-demo/vehiculos/crear')->assertForbidden();
        $this->get("/app/cootrasec-demo/vehiculos/{$v->id}/editar")->assertForbidden();
    }

    public function test_el_auxiliar_registra_vehiculos(): void
    {
        $this->actingAs($this->usuario('auxiliar@demo.test'));
        $this->get('/app/cootrasec-demo/vehiculos/crear')->assertOk();
        $this->get('/app/cootrasec-demo/vencimientos')->assertOk();
    }

    public function test_no_se_ven_vehiculos_de_otra_empresa(): void
    {
        $v = $this->vehiculo('DMO101');

        $this->actingAs($this->usuario('admin@ejemplo.test'));
        $this->get("/app/transportes-ejemplo/vehiculos/{$v->id}")->assertNotFound();

        $this->entrarComo('admin@ejemplo.test', $this->otra);
        $this->assertSame(0, Vehiculo::count());
        $this->assertSame(0, DocumentoVehiculo::count());
    }

    public function test_crear_vehiculo_normaliza_la_placa_y_no_la_repite(): void
    {
        $this->entrarComo('admin@demo.test');

        $datos = [
            'propietario_id' => Propietario::first()->id,
            'marca_id' => Marca::firstWhere('nombre', 'Toyota')->id,
            'modelo' => 2024,
            'clase_vehiculo_id' => ClaseVehiculo::firstWhere('nombre', 'Van')->id,
        ];

        Livewire::test(CrearVehiculo::class)
            ->fillForm([...$datos, 'placa' => 'xyz-987'])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = Vehiculo::firstWhere('placa', 'XYZ987');
        $this->assertNotNull($nuevo);
        $this->assertSame($this->demo->id, $nuevo->empresa_id);
        $this->assertSame(EstadoVehiculo::Operando, $nuevo->estado);

        Livewire::test(CrearVehiculo::class)
            ->fillForm([...$datos, 'placa' => 'XYZ 987'])
            ->call('create')
            ->assertHasFormErrors(['placa']);

        Livewire::test(CrearVehiculo::class)
            ->fillForm([...$datos, 'placa' => '12345'])
            ->call('create')
            ->assertHasFormErrors(['placa']);

        // Otra empresa sí puede tener la misma placa (p. ej. tras un traslado).
        $propietario = Propietario::create(['empresa_id' => $this->otra->id, 'documento' => '123', 'nombres' => 'Otro']);
        $otro = Vehiculo::create([...$datos, 'empresa_id' => $this->otra->id, 'propietario_id' => $propietario->id, 'placa' => 'XYZ987']);
        $this->assertSame($this->otra->id, $otro->empresa_id);
    }

    public function test_el_semaforo_y_los_bloqueos_siguen_al_documento_vigente(): void
    {
        $v = $this->vehiculo('DMO103')->load('documentos');

        $this->assertSame(EstadoDocumento::Vencido, $v->estadoDocumental());
        $this->assertFalse($v->puedeOperar());

        // Renovar = registro nuevo; el anterior queda en el historial.
        $antes = $v->documentos()->where('tipo', TipoDocumentoVehiculo::Soat)->count();
        $v->documentos()->create([
            'tipo' => TipoDocumentoVehiculo::Soat,
            'fecha_expedicion' => today(),
            'fecha_vencimiento' => today()->addYear(),
        ]);
        $v->load('documentos');

        $this->assertSame($antes + 1, $v->documentos()->where('tipo', TipoDocumentoVehiculo::Soat)->count());
        $this->assertTrue($v->puedeOperar());
        $this->assertTrue($v->documentoVigente(TipoDocumentoVehiculo::Soat)->fecha_vencimiento->isSameDay(today()->addYear()));
        $this->assertSame(1, DocumentoVehiculo::query()->deEmpresa($this->demo)->vigentes()
            ->where('vehiculo_id', $v->id)->where('tipo', TipoDocumentoVehiculo::Soat)->count());

        // Documento faltante bloquea.
        $sinTarjeta = $this->vehiculo('DMO104')->load('documentos');
        $this->assertSame(EstadoDocumento::Faltante, $sinTarjeta->estadoDocumental());
        $this->assertStringContainsString('Tarjeta de operación', $sinTarjeta->bloqueosOperacion()[0]);
    }

    public function test_cambiar_estado_deja_historial_y_valida_transiciones(): void
    {
        $this->entrarComo('admin@demo.test');
        $v = $this->vehiculo('DMO101');

        Livewire::test(VerVehiculo::class, ['record' => $v->getKey()])
            ->callAction('cambiarEstado', [
                'tipo' => TipoNovedadVehiculo::Suspension->value,
                'fecha' => today()->toDateString(),
                'observacion' => 'Suspendido por prueba',
            ])
            ->assertHasNoActionErrors();

        $v->refresh();
        $this->assertSame(EstadoVehiculo::Suspendido, $v->estado);
        $this->assertSame(1, $v->novedades()->count());
        $this->assertSame(EstadoVehiculo::Operando, $v->novedades()->first()->estado_anterior);
        $this->assertContains('El vehículo está suspendido.', $v->bloqueosOperacion());

        // No se puede vincular un vehículo que no está desvinculado.
        $this->expectException(ValidationException::class);
        app(CambiarEstadoVehiculo::class)($v, TipoNovedadVehiculo::Vinculacion, 'x');
    }

    public function test_un_vehiculo_desvinculado_sale_de_vencimientos(): void
    {
        $this->entrarComo('admin@demo.test');
        $v = $this->vehiculo('DMO103');

        Livewire::test(ListarVencimientos::class)
            ->assertCanSeeTableRecords(DocumentoVehiculo::query()->vigentes()->where('vehiculo_id', $v->id)->whereDate('fecha_vencimiento', '<', today())->get());

        app(CambiarEstadoVehiculo::class)($v, TipoNovedadVehiculo::Desvinculacion, 'Sale de la flota');

        Livewire::test(ListarVencimientos::class)
            ->assertCanNotSeeTableRecords(DocumentoVehiculo::query()->where('vehiculo_id', $v->id)->get());
    }

    public function test_renovar_desde_la_hoja_de_vida(): void
    {
        $this->entrarComo('admin@demo.test');
        $v = $this->vehiculo('DMO103');
        $vencido = $v->documentos()->vigentes()->where('tipo', TipoDocumentoVehiculo::Soat)->first();

        Livewire::test(DocumentosRelationManager::class, ['ownerRecord' => $v, 'pageClass' => VerVehiculo::class])
            ->callTableAction('renovar', $vencido, [
                'numero' => 'SOAT-NUEVO',
                'aseguradora_id' => $vencido->aseguradora_id,
                'fecha_expedicion' => today()->toDateString(),
                'fecha_vencimiento' => today()->addYear()->subDay()->toDateString(),
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($v->refresh()->load('documentos')->puedeOperar());
        $this->assertSame('SOAT-NUEVO', $v->documentoVigente(TipoDocumentoVehiculo::Soat)->numero);
        $this->assertNotNull(DocumentoVehiculo::find($vencido->id), 'El documento anterior se conserva en el historial.');
    }

    public function test_desplegar_no_borra_los_ajustes_de_roles_de_la_empresa(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->demo->id);
        $auxiliar = Rol::query()->deEmpresa($this->demo)->where('name', Rol::AUXILIAR)->firstOrFail();
        $auxiliar->revokePermissionTo('crear_vehiculos');
        $auxiliar->givePermissionTo('eliminar_vehiculos');

        $this->seed(PermisosSeeder::class); // corre en cada despliegue

        $auxiliar = $auxiliar->fresh();
        $this->assertFalse($auxiliar->hasPermissionTo('crear_vehiculos'));
        $this->assertTrue($auxiliar->hasPermissionTo('eliminar_vehiculos'));
    }
}
