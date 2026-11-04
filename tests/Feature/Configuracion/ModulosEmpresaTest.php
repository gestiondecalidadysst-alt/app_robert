<?php

namespace Tests\Feature\Configuracion;

use App\Enums\ModuloEmpresa;
use App\Filament\Admin\Resources\Empresas\Pages\EditarEmpresa;
use App\Models\Conductor;
use App\Models\Fuec;
use App\Models\Inspeccion;
use App\Models\Vehiculo;
use Filament\Facades\Filament;
use Livewire\Livewire;

class ModulosEmpresaTest extends ConfiguracionTestCase
{
    /** Pantallas (sin el prefijo /app/{empresa}) de cada módulo. */
    private const PANTALLAS = [
        'base' => ['', '/profile', '/oficinas', '/usuarios', '/roles', '/integraciones', '/auditoria', '/valores-administracion',
            '/vehiculos', '/propietarios', '/conductores', '/vencimientos', '/vencimientos-conductores'],
        'pasajeros' => ['/clientes', '/contratos', '/fuec', '/fuec/expedir', '/planeador', '/servicios', '/servicios/planificar',
            '/rutogramas', '/plantilla-contrato', '/empresas-convenio'],
        'seguridad_vial' => ['/inspecciones', '/inspecciones/mensual', '/inspecciones/pendientes-hoy', '/items-chequeo'],
        'mantenimiento' => ['/mantenimientos', '/mantenimientos/registrar', '/planes-accion', '/plan-preventivo', '/programa-mantenimiento',
            '/calendario-mantenimiento', '/plantillas-mantenimiento'],
        'gerencia' => ['/gerencia', '/informes'],
    ];

    private function soloBase(): void
    {
        $this->demo->update(['modulos' => []]);
    }

    public function test_las_empresas_existentes_y_nuevas_tienen_lo_construido(): void
    {
        $this->assertSame(ModuloEmpresa::porDefecto(), $this->demo->refresh()->modulos);
        foreach (ModuloEmpresa::porDefecto() as $m) {
            $this->assertTrue($this->demo->tieneModulo(ModuloEmpresa::from($m)));
        }
        $this->assertFalse($this->demo->tieneModulo(ModuloEmpresa::Carga));
    }

    public function test_con_todos_los_modulos_abren_todas_las_pantallas(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));
        foreach (self::PANTALLAS as $grupo => $rutas) {
            foreach ($rutas as $ruta) {
                $this->get('/app/cootrasec-demo' . $ruta)->assertOk();
            }
        }
    }

    public function test_sin_modulos_solo_queda_la_base_tambien_para_el_superadministrador(): void
    {
        $this->soloBase();

        foreach (['admin@demo.test', 'superadmin@ruta.test'] as $email) {
            $this->flushSession();
            $this->actingAs($this->usuario($email));
            foreach (self::PANTALLAS as $grupo => $rutas) {
                foreach ($rutas as $ruta) {
                    $respuesta = $this->get('/app/cootrasec-demo' . $ruta);
                    $grupo === 'base'
                        ? $respuesta->assertOk()
                        : $respuesta->assertForbidden();
                }
            }
        }

        // Inicio sin los cuadros de contratos y extractos; menú sin Operación, Gerencia ni Mantenimiento.
        $this->flushSession();
        $this->actingAs($this->usuario('admin@demo.test'));
        $this->get('/app/cootrasec-demo')->assertOk()
            ->assertSee('Vehículos')->assertSee('Flota y conductores')
            ->assertDontSee('Planeador')->assertDontSee('FUEC')->assertDontSee('Tablero')->assertDontSee('Planes de acción');
        $this->get('/app/cootrasec-demo/integraciones')->assertOk()
            ->assertDontSee('Firma electrónica de contratos')->assertDontSee('Recibos de caja')->assertSee('Mensajes de texto');
        $rol = \App\Models\Rol::query()->deEmpresa($this->demo)->where('name', \App\Models\Rol::AUXILIAR)->firstOrFail();
        $this->get("/app/cootrasec-demo/roles/{$rol->id}/editar")->assertOk()->assertDontSee('FUEC (extractos)')->assertSee('Vehículos');

        // La otra empresa no se afecta.
        $this->assertTrue($this->otra->refresh()->tieneModulo(ModuloEmpresa::Pasajeros));
    }

    public function test_un_modulo_suelto_y_los_permisos_se_conservan(): void
    {
        $this->demo->update(['modulos' => [ModuloEmpresa::Gerencia->value]]);
        $this->actingAs($this->usuario('gerencia@demo.test'));
        $this->get('/app/cootrasec-demo/gerencia')->assertOk();
        $this->get('/app/cootrasec-demo/contratos')->assertForbidden();

        // Guardar Integraciones con secciones ocultas no pierde sus valores.
        $this->demo->update(['firma_dias_enlace' => 9]);
        $this->entrarComo('admin@demo.test');
        Livewire::test(\App\Filament\App\Pages\Integraciones::class)->call('guardar')->assertHasNoErrors();
        $this->assertSame(9, (int) $this->demo->refresh()->firma_dias_enlace);

        // Al volver a contratar, los roles conservan sus permisos.
        $this->demo->update(['modulos' => ModuloEmpresa::porDefecto()]);
        $this->flushSession();
        $this->actingAs($this->usuario('auxiliar@demo.test'));
        $this->get('/app/cootrasec-demo/fuec')->assertOk();
    }

    public function test_el_superadministrador_elige_los_modulos(): void
    {
        $this->actingAs($this->usuario('superadmin@ruta.test'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get('/admin/empresas/' . $this->demo->id . '/editar')->assertOk()->assertSee('Módulos contratados')->assertSee('Manifiestos de carga');
        Livewire::test(EditarEmpresa::class, ['record' => $this->demo->getRouteKey()])
            ->fillForm(['modulos' => [ModuloEmpresa::Mantenimiento->value]])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame([ModuloEmpresa::Mantenimiento->value], $this->demo->refresh()->modulos);
        $this->get('/admin/empresas')->assertOk();
    }

    public function test_portal_del_conductor_sin_seguridad_vial_ni_mantenimiento(): void
    {
        $this->demo->update(['modulos' => [ModuloEmpresa::Pasajeros->value]]);
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->firstOrFail();
        $fuec = Fuec::query()->deEmpresa($this->demo)->where('vehiculo_id', $vehiculo->id)->firstOrFail();

        $this->post('/conductor/ingresar', ['documento' => '20.000.001', 'clave' => 'Ruta2026*'])->assertRedirect(route('conductor.inicio'));

        // El extracto se ve sin preoperacional; no hay preoperacional, mantenimiento ni pedido de firma.
        $this->get('/conductor')->assertOk()->assertSee($fuec->numero_formateado)
            ->assertSee('Ver extracto')->assertDontSee('Hacer preoperacional')->assertDontSee('Falta su firma')
            ->assertDontSee('>Preoperacionales<', false)->assertDontSee('>Mantenimiento<', false);
        $this->get(route('conductor.extracto', $fuec))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('conductor.preoperacional', $vehiculo))->assertNotFound();
        $this->get('/conductor/historial')->assertNotFound();
        $this->get('/conductor/mantenimiento')->assertNotFound();
        $this->get(route('conductor.mantenimiento.registrar', $vehiculo))->assertNotFound();

        // Sin pasajeros, el conductor no ve los FUEC.
        $this->demo->update(['modulos' => [ModuloEmpresa::SeguridadVial->value]]);
        $this->get('/conductor')->assertOk()->assertDontSee($fuec->numero_formateado)->assertSee('Hacer preoperacional');
    }

    public function test_preoperacional_sin_mantenimiento_no_abre_planes_de_accion(): void
    {
        $this->demo->update(['modulos' => [ModuloEmpresa::Pasajeros->value, ModuloEmpresa::SeguridadVial->value]]);
        $this->entrarComo('admin@demo.test');
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->firstOrFail();
        $item = \App\Models\ItemChequeo::query()->deEmpresa($this->demo)->deTipo(\App\Enums\TipoInspeccion::Preoperacional)->where('critico', true)->firstOrFail();
        $respuestas = \App\Models\ItemChequeo::query()->deEmpresa($this->demo)->deTipo(\App\Enums\TipoInspeccion::Preoperacional)->activos()->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => ['respuesta' => 'si']])->all();
        $respuestas[$item->id] = ['respuesta' => 'no', 'observacion' => 'Llanta lisa'];

        $antes = \App\Models\PlanAccion::query()->deEmpresa($this->demo)->count();
        $inspeccion = app(\App\Actions\Pesv\RegistrarInspeccion::class)(
            $this->demo, \App\Enums\TipoInspeccion::Preoperacional, $vehiculo, $respuestas,
            Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->first(), null, 130500, null, [], $this->usuario('admin@demo.test'),
        );
        $this->assertFalse($inspeccion->apta(), 'La falla crítica sigue dejando NO APTO.');
        $this->assertSame($antes, \App\Models\PlanAccion::query()->deEmpresa($this->demo)->count(), 'Sin mantenimiento no se abren planes.');
    }
}
