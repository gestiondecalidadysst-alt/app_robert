<?php

namespace Tests\Feature\Operacion;

use App\Actions\Operacion\AnularFuec;
use App\Actions\Operacion\ExpedirFuec;
use App\Enums\ObjetoContrato;
use App\Models\Conductor;
use App\Models\Contrato;
use App\Models\Fuec;
use App\Models\Municipio;
use App\Models\Vehiculo;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Listado de pasajeros por FUEC (Turismo / Grupo específico): se abre al expedir,
 * los pasajeros se autorregistran por el enlace público y el operador lo cierra.
 */
class PasajerosFuecTest extends ConfiguracionTestCase
{
    private function contrato(): Contrato
    {
        return Contrato::query()->deEmpresa($this->demo)->where('numero', 1)->firstOrFail();
    }

    private function datos(array $cambios = []): array
    {
        $monteria = Municipio::firstWhere('codigo_dane', '23001')->id;

        return array_merge([
            'contrato_id' => $this->contrato()->id,
            'vehiculo_id' => Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->firstOrFail()->id,
            'conductores' => [Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->firstOrFail()->id],
            'fecha_inicio' => today()->toDateString(),
            'fecha_fin' => today()->addDays(5)->toDateString(),
            'hora_inicio' => '16:00',
            'hora_fin' => '20:00',
            'origen_municipio_id' => $monteria,
            'destino_municipio_id' => $monteria,
        ], $cambios);
    }

    public function test_abre_listado_solo_para_turismo_o_grupo_especifico(): void
    {
        $this->entrarComo('admin@demo.test');

        $this->contrato()->forceFill(['objeto' => ObjetoContrato::Turismo])->save();
        $fuec = app(ExpedirFuec::class)($this->demo, $this->datos());
        $this->assertTrue($fuec->tieneListadoPasajeros());
        $this->assertSame('abierto', $fuec->pasajeros_estado);
        $this->assertNotNull($fuec->pasajeros_token);

        // Otra modalidad no abre listado (horas distintas para no cruzar con el anterior).
        $this->contrato()->forceFill(['objeto' => ObjetoContrato::Empresarial])->save();
        $fuec2 = app(ExpedirFuec::class)($this->demo, $this->datos(['hora_inicio' => '21:00', 'hora_fin' => '23:00']));
        $this->assertFalse($fuec2->tieneListadoPasajeros());
    }

    public function test_autorregistro_agrega_pasajero_y_respeta_reglas(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->entrarComo('admin@demo.test');
        $this->contrato()->forceFill(['objeto' => ObjetoContrato::Turismo])->save();
        $fuec = app(ExpedirFuec::class)($this->demo, $this->datos());
        $token = $fuec->pasajeros_token;

        $this->get(route('pasajeros.registro', $token))->assertOk()->assertSee('Registro de pasajeros');

        $this->post(route('pasajeros.registrar', $token), [
            'nombre' => 'Ana Gómez', 'tipo_documento' => 'CC', 'documento' => '123',
        ])->assertRedirect()->assertSessionHas('estado', 'ok');
        $this->assertSame(1, $fuec->pasajeros()->count());

        // Documento duplicado: no agrega otro.
        $this->post(route('pasajeros.registrar', $token), [
            'nombre' => 'Otro', 'tipo_documento' => 'CC', 'documento' => '123',
        ])->assertSessionHas('estado', 'duplicado');
        $this->assertSame(1, $fuec->pasajeros()->count());

        // Menor exige acudiente.
        $this->post(route('pasajeros.registrar', $token), [
            'nombre' => 'Niño', 'tipo_documento' => 'TI', 'documento' => '999', 'es_menor' => '1',
        ])->assertSessionHasErrors('acudiente_nombre');
        $this->assertSame(1, $fuec->pasajeros()->count());

        // Cerrado por el operador: no registra.
        $fuec->cerrarListadoPasajeros($this->usuario('admin@demo.test')->id);
        $this->post(route('pasajeros.registrar', $token), [
            'nombre' => 'Tarde', 'tipo_documento' => 'CC', 'documento' => '555',
        ])->assertSessionHas('estado', 'cerrado');
        $this->assertSame(1, $fuec->pasajeros()->count());
        $this->assertTrue($fuec->fresh()->listadoCerrado());
    }

    public function test_respeta_la_capacidad_del_vehiculo(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->entrarComo('admin@demo.test');
        $this->contrato()->forceFill(['objeto' => ObjetoContrato::GrupoEspecifico])->save();
        Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->firstOrFail()
            ->forceFill(['capacidad_pasajeros' => 1])->save();
        $fuec = app(ExpedirFuec::class)($this->demo, $this->datos());
        $token = $fuec->pasajeros_token;

        $this->post(route('pasajeros.registrar', $token), ['nombre' => 'Uno', 'tipo_documento' => 'CC', 'documento' => '1']);
        $this->assertSame(1, $fuec->pasajeros()->count());

        $this->post(route('pasajeros.registrar', $token), ['nombre' => 'Dos', 'tipo_documento' => 'CC', 'documento' => '2'])
            ->assertSessionHas('estado', 'lleno');
        $this->assertSame(1, $fuec->pasajeros()->count());
    }

    public function test_el_conductor_ve_el_listado_de_su_extracto(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->entrarComo('admin@demo.test');
        $this->contrato()->forceFill(['objeto' => ObjetoContrato::Turismo])->save();
        $fuec = app(ExpedirFuec::class)($this->demo, $this->datos());
        $this->post(route('pasajeros.registrar', $fuec->pasajeros_token), [
            'nombre' => 'Ana Gómez', 'tipo_documento' => 'CC', 'documento' => '123456789',
        ]);

        $this->post('/conductor/ingresar', ['documento' => '20.000.001', 'clave' => 'Ruta2026*'])
            ->assertRedirect(route('conductor.inicio'));

        $this->get(route('conductor.pasajeros', $fuec))
            ->assertOk()
            ->assertSee('Listado de pasajeros')
            ->assertSee('Ana Gómez');
    }

    public function test_verificacion_publica_muestra_documento_parcial(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->entrarComo('admin@demo.test');
        $this->contrato()->forceFill(['objeto' => ObjetoContrato::Turismo])->save();
        $fuec = app(ExpedirFuec::class)($this->demo, $this->datos());
        $this->post(route('pasajeros.registrar', $fuec->pasajeros_token), [
            'nombre' => 'Ana Gómez', 'tipo_documento' => 'CC', 'documento' => '123456789',
        ]);

        $r = $this->get(route('listado.verificar', $fuec->pasajeros_codigo));
        $r->assertOk()
            ->assertSee('Listado verificado')
            ->assertSee('Ana Gómez')
            ->assertSee('789')
            ->assertDontSee('123456789');
    }

    public function test_hay_tope_de_seguridad_aunque_el_vehiculo_no_tenga_capacidad(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->contrato()->forceFill(['objeto' => ObjetoContrato::Turismo])->save();
        Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->firstOrFail()
            ->forceFill(['capacidad_pasajeros' => null])->save();
        $fuec = app(ExpedirFuec::class)($this->demo, $this->datos());

        // Sin capacidad no se muestra cupo, pero el límite real es finito (tope de seguridad).
        $this->assertNull($fuec->cuposDisponibles());
        $this->assertSame(Fuec::TOPE_PASAJEROS, $fuec->limitePasajeros());
    }

    public function test_un_fuec_anulado_no_acepta_registros_y_se_marca_en_la_verificacion(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->entrarComo('admin@demo.test');
        $this->contrato()->forceFill(['objeto' => ObjetoContrato::Turismo])->save();
        $fuec = app(ExpedirFuec::class)($this->demo, $this->datos());
        $token = $fuec->pasajeros_token;

        app(AnularFuec::class)($fuec, 'Prueba de anulación');
        $this->assertTrue($fuec->fresh()->listadoCerrado());

        $this->post(route('pasajeros.registrar', $token), [
            'nombre' => 'Tarde', 'tipo_documento' => 'CC', 'documento' => '9',
        ])->assertSessionHas('estado', 'cerrado');
        $this->assertSame(0, $fuec->pasajeros()->count());

        $this->get(route('listado.verificar', $fuec->pasajeros_codigo))
            ->assertOk()->assertSee('ANULADO');
    }
}
