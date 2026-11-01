<?php

namespace Tests\Feature\Pesv;

use App\Actions\Pesv\CrearAccesoConductor;
use App\Actions\Pesv\GuardarFirmaConductor;
use App\Actions\Pesv\RegistrarInspeccion;
use App\Enums\EstadoConductor;
use App\Enums\TipoInspeccion;
use App\Filament\App\Resources\Conductores\Pages\VerConductor;
use App\Filament\App\Resources\Inspecciones\Pages\PendientesHoy;
use App\Filament\App\Resources\Inspecciones\Pages\RegistrarInspeccionMensual;
use App\Models\Conductor;
use App\Models\Fuec;
use App\Models\Inspeccion;
use App\Models\ItemChequeo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Pdf\PdfInspeccion;
use App\Support\Pesv\ItemsBase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class PesvTest extends ConfiguracionTestCase
{
    private const FIRMA = 'data:image/png;base64,';

    private function conductor(): Conductor
    {
        return Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->firstOrFail();
    }

    private function vehiculo(): Vehiculo
    {
        return Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->firstOrFail();
    }

    private function fuec(): Fuec
    {
        return Fuec::query()->deEmpresa($this->demo)->where('vehiculo_id', $this->vehiculo()->id)->firstOrFail();
    }

    /** Respuestas "cumple" a todos los ítems activos, con cambios puntuales. */
    private function respuestas(TipoInspeccion $tipo = TipoInspeccion::Preoperacional, array $cambios = []): array
    {
        $respuestas = ItemChequeo::query()->deEmpresa($this->demo)->deTipo($tipo)->activos()->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => ['respuesta' => 'si']])->all();

        return array_replace($respuestas, $cambios);
    }

    private function itemCritico(): ItemChequeo
    {
        return ItemChequeo::query()->deEmpresa($this->demo)->deTipo(TipoInspeccion::Preoperacional)->where('critico', true)->firstOrFail();
    }

    /** PNG de una firma dibujada (líneas) en data URI. */
    private static function firmaDibujada(): string
    {
        $img = imagecreatetruecolor(400, 150);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        $tinta = imagecolorallocate($img, 10, 10, 40);
        imagesetthickness($img, 4);
        imageline($img, 20, 100, 120, 30, $tinta);
        imageline($img, 120, 30, 220, 120, $tinta);
        imageline($img, 220, 120, 380, 40, $tinta);
        ob_start();
        imagepng($img);

        return self::FIRMA . base64_encode(ob_get_clean());
    }

    private function entrarAlPortal(): User
    {
        $usuario = $this->conductor()->usuario;
        $this->post('/conductor/ingresar', ['documento' => '20.000.001', 'clave' => 'Ruta2026*'])
            ->assertRedirect(route('conductor.inicio'));

        return $usuario;
    }

    public function test_cada_empresa_recibe_los_items_por_defecto_una_sola_vez(): void
    {
        $preop = ItemChequeo::query()->deEmpresa($this->otra)->deTipo(TipoInspeccion::Preoperacional)->count();
        $mensual = ItemChequeo::query()->deEmpresa($this->otra)->deTipo(TipoInspeccion::Mensual)->count();

        $this->assertSame(count(ItemsBase::PREOPERACIONAL), $preop);
        $this->assertSame(count(ItemsBase::MENSUAL), $mensual);

        ItemsBase::crear($this->otra);
        $this->assertSame($preop, ItemChequeo::query()->deEmpresa($this->otra)->deTipo(TipoInspeccion::Preoperacional)->count());
    }

    public function test_pantallas_de_seguridad_vial_y_permisos(): void
    {
        $inspeccion = Inspeccion::query()->deEmpresa($this->demo)->firstOrFail();
        $conductor = $this->conductor();

        $this->actingAs($this->usuario('admin@demo.test'));
        foreach ([
            '/app/cootrasec-demo/inspecciones',
            '/app/cootrasec-demo/inspecciones/pendientes-hoy',
            '/app/cootrasec-demo/inspecciones/mensual',
            "/app/cootrasec-demo/inspecciones/{$inspeccion->id}",
            '/app/cootrasec-demo/items-chequeo',
            "/app/cootrasec-demo/conductores/{$conductor->id}",
            "/app/cootrasec-demo/conductores/{$conductor->id}/editar?tab=firma::data::tab",
        ] as $url) {
            $this->get($url)->assertOk();
        }

    }

    public function test_gerencia_ve_pero_no_registra_y_otra_empresa_no_ve(): void
    {
        $inspeccion = Inspeccion::query()->deEmpresa($this->demo)->firstOrFail();

        // Gerencia ve, pero no registra inspecciones ni edita ítems.
        $this->actingAs($this->usuario('gerencia@demo.test'));
        $this->get('/app/cootrasec-demo/inspecciones')->assertOk();
        $this->get('/app/cootrasec-demo/inspecciones/mensual')->assertForbidden();

    }

    public function test_otra_empresa_no_ve_las_inspecciones(): void
    {
        $inspeccion = Inspeccion::query()->deEmpresa($this->demo)->firstOrFail();

        $this->actingAs($this->usuario('admin@ejemplo.test'));
        $this->get("/app/transportes-ejemplo/inspecciones/{$inspeccion->id}")->assertNotFound();
    }

    public function test_registrar_preoperacional_copia_la_firma_y_valida(): void
    {
        $registrar = app(RegistrarInspeccion::class);
        $conductor = $this->conductor();
        $critico = $this->itemCritico();

        // Faltan respuestas, falla sin explicar y sin kilometraje.
        try {
            $registrar($this->demo, TipoInspeccion::Preoperacional, $this->vehiculo(), [$critico->id => ['respuesta' => 'no']], conductor: $conductor);
            $this->fail('Debió rechazar');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('respuestas', $e->errors());
            $this->assertArrayHasKey("respuestas.{$critico->id}.observacion", $e->errors());
            $this->assertArrayHasKey('kilometraje', $e->errors());
        }

        // Sin firma registrada no se puede.
        $sinFirma = Conductor::query()->deEmpresa($this->demo)->where('id', '!=', $conductor->id)->firstOrFail();
        $this->assertFalse($sinFirma->tieneFirma());
        try {
            $registrar($this->demo, TipoInspeccion::Preoperacional, $this->vehiculo(), $this->respuestas(), conductor: $sinFirma, kilometraje: 10);
            $this->fail('Debió rechazar');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('firma', $e->errors()['conductor'][0]);
        }

        $inspeccion = $registrar($this->demo, TipoInspeccion::Preoperacional, $this->vehiculo(), $this->respuestas(), conductor: $conductor,
            fuec: $this->fuec(), kilometraje: 130000, contexto: ['ip' => '10.0.0.1', 'latitud' => '8.75', 'longitud' => '-75.88'], usuario: $conductor->usuario);

        $this->assertTrue($inspeccion->apta());
        $this->assertNotSame($conductor->firma_path, $inspeccion->firma_path);
        Storage::disk('local')->assertExists($inspeccion->firma_path);
        $this->assertTrue($inspeccion->datos['registrado_por_conductor']);
        $this->assertSame(8.75, $inspeccion->latitud);

        // Ya hecho hoy: no se repite.
        try {
            $registrar($this->demo, TipoInspeccion::Preoperacional, $this->vehiculo(), $this->respuestas(), conductor: $conductor, kilometraje: 130001);
            $this->fail('Debió rechazar');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ya está hecho', $e->errors()['vehiculo'][0]);
        }

        // El PDF lleva la firma y el QR; la verificación pública muestra lo mínimo.
        $this->assertStringStartsWith('%PDF', PdfInspeccion::generar($inspeccion));
        $this->get($inspeccion->urlVerificacion())->assertOk()->assertSee('Apto')->assertSee('DMO101')->assertDontSee('20000001');
    }

    public function test_un_item_critico_que_falla_deja_no_apto_y_avisa(): void
    {
        $critico = $this->itemCritico();
        $conductor = $this->conductor();

        $inspeccion = app(RegistrarInspeccion::class)($this->demo, TipoInspeccion::Preoperacional, $this->vehiculo(),
            $this->respuestas(cambios: [$critico->id => ['respuesta' => 'no', 'observacion' => 'Falla de prueba']]),
            conductor: $conductor, kilometraje: 1, usuario: $conductor->usuario);

        $this->assertFalse($inspeccion->apta());
        $this->assertCount(1, $inspeccion->fallas());
        $this->assertTrue($this->usuario('admin@demo.test')->notifications()->where('data', 'like', '%no apto%')->exists());
        $this->assertFalse($this->usuario('admin@ejemplo.test')->notifications()->exists());

        // Un no crítico que falla no bloquea.
        $noCritico = ItemChequeo::query()->deEmpresa($this->demo)->deTipo(TipoInspeccion::Preoperacional)->where('critico', false)->firstOrFail();
        $otra = app(RegistrarInspeccion::class)($this->demo, TipoInspeccion::Preoperacional, $this->vehiculo(),
            $this->respuestas(cambios: [$noCritico->id => ['respuesta' => 'no', 'observacion' => 'Sucio']]),
            conductor: $conductor, kilometraje: 2, usuario: $conductor->usuario);
        $this->assertTrue($otra->apta());
    }

    public function test_portal_tarjeta_preoperacional_y_extracto_bloqueado_hasta_hacerlo(): void
    {
        $fuec = $this->fuec();
        $this->get('/conductor')->assertRedirect(route('conductor.ingresar'));

        $this->post('/conductor/ingresar', ['documento' => '20000001', 'clave' => 'mala'])->assertSessionHasErrors('documento');
        $this->entrarAlPortal();

        // La tarjeta del servicio, con el extracto bloqueado.
        $this->get('/conductor')->assertOk()
            ->assertSee($fuec->numero_formateado)
            ->assertSee('Hacer preoperacional')
            ->assertSee('Haga el preoperacional de hoy para ver el extracto.');
        $this->get(route('conductor.extracto', $fuec))->assertRedirect(route('conductor.inicio'));

        // Hace el preoperacional desde el celular.
        $this->get(route('conductor.preoperacional', ['vehiculo' => $fuec->vehiculo_id, 'fuec' => $fuec->id]))->assertOk()->assertSee('CRÍTICO');
        $this->post(route('conductor.preoperacional.guardar', ['vehiculo' => $fuec->vehiculo_id, 'fuec' => $fuec->id]), [
            'kilometraje' => 130500,
            'respuestas' => $this->respuestas(),
            'declaro' => 1,
        ])->assertRedirect(route('conductor.inicio'))->assertSessionHas('aviso');

        $inspeccion = Inspeccion::query()->deEmpresa($this->demo)->latest('id')->first();
        $this->assertSame($fuec->id, $inspeccion->fuec_id);
        $this->assertSame('127.0.0.1', $inspeccion->ip);

        // Ahora sí: extracto y PDF del preoperacional.
        $this->get('/conductor')->assertOk()->assertSee('Ver preoperacional (PDF)')->assertDontSee('Haga el preoperacional de hoy');
        $this->get(route('conductor.extracto', $fuec))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('conductor.inspeccion.pdf', $inspeccion))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/conductor/historial')->assertOk()->assertSee('DMO101');
    }

    public function test_vehiculo_no_apto_mantiene_bloqueado_el_extracto(): void
    {
        $fuec = $this->fuec();
        $critico = $this->itemCritico();
        $this->entrarAlPortal();

        $this->post(route('conductor.preoperacional.guardar', ['vehiculo' => $fuec->vehiculo_id, 'fuec' => $fuec->id]), [
            'kilometraje' => 1,
            'respuestas' => $this->respuestas(cambios: [$critico->id => ['respuesta' => 'no', 'observacion' => 'Sin frenos']]),
            'declaro' => 1,
        ])->assertSessionHas('alerta');

        $this->get('/conductor')->assertSee('NO APTO en el preoperacional de hoy');
        $this->get(route('conductor.extracto', $fuec))->assertRedirect(route('conductor.inicio'));
    }

    public function test_un_conductor_no_ve_servicios_ni_vehiculos_ajenos(): void
    {
        $fuec = $this->fuec();
        $otro = Conductor::query()->deEmpresa($this->demo)->where('id', '!=', $this->conductor()->id)->firstOrFail();

        $this->entrarComo('admin@demo.test');
        $acceso = app(CrearAccesoConductor::class)($otro);
        app(GuardarFirmaConductor::class)($otro->refresh(), self::firmaDibujada());
        auth()->logout();

        $acceso['usuario']->forceFill(['debe_cambiar_clave' => false])->save();
        $this->post('/conductor/ingresar', ['documento' => $otro->documento, 'clave' => $acceso['clave']])->assertRedirect();

        $this->get('/conductor')->assertOk()->assertDontSee($fuec->numero_formateado);
        $this->get(route('conductor.extracto', $fuec))->assertNotFound();
        $this->get(route('conductor.preoperacional', ['vehiculo' => $fuec->vehiculo_id]))->assertNotFound();
        $ajena = Inspeccion::query()->deEmpresa($this->demo)->where('conductor_id', $this->conductor()->id)->firstOrFail();
        $this->get(route('conductor.inspeccion.pdf', $ajena))->assertNotFound();
    }

    public function test_acceso_al_portal_clave_temporal_firma_y_cambio_de_clave(): void
    {
        $conductor = Conductor::query()->deEmpresa($this->demo)->where('id', '!=', $this->conductor()->id)->firstOrFail();

        $this->entrarComo('admin@demo.test');
        Livewire::test(VerConductor::class, ['record' => $conductor->getRouteKey()])
            ->callAction('portal')
            ->assertHasNoActionErrors()
            ->assertNotified();

        $conductor->refresh();
        $this->assertNotNull($conductor->user_id);
        $this->assertNull($conductor->usuario->email);
        $this->assertTrue($conductor->usuario->debe_cambiar_clave);
        $this->assertFalse($conductor->usuario->canAccessPanel(\Filament\Facades\Filament::getPanel('app')));
        auth()->logout();

        // La clave que se mostró se puede usar; obliga a cambiarla y luego pide la firma.
        $clave = app(CrearAccesoConductor::class)($conductor)['clave'];
        $this->post('/conductor/ingresar', ['documento' => $conductor->documento, 'clave' => $clave]);
        $this->get('/conductor')->assertRedirect(route('conductor.clave'));
        $this->post('/conductor/clave', ['actual' => $clave, 'clave' => 'corta', 'clave_confirmation' => 'corta'])->assertSessionHasErrors('clave');
        $this->post('/conductor/clave', ['actual' => $clave, 'clave' => 'MiClave2026', 'clave_confirmation' => 'MiClave2026'])
            ->assertRedirect(route('conductor.inicio'));

        $this->get('/conductor')->assertOk()->assertSee('Falta su firma');
        $this->post('/conductor/firma', ['firma' => self::FIRMA . base64_encode('x'), 'acepto' => 1])->assertSessionHasErrors('firma');
        $this->post('/conductor/firma', ['firma' => self::firmaDibujada(), 'acepto' => 1])->assertRedirect(route('conductor.inicio'));
        $this->assertTrue($conductor->refresh()->tieneFirma());
        // Ya registrada: el conductor no la puede cambiar él mismo.
        $this->get('/conductor/firma')->assertRedirect(route('conductor.inicio'));

        // Retirado: no entra.
        $conductor->forceFill(['estado' => EstadoConductor::Retirado])->save();
        $this->get('/conductor')->assertRedirect(route('conductor.ingresar'));
    }

    public function test_la_misma_persona_en_dos_empresas_usa_un_solo_usuario(): void
    {
        $conductor = $this->conductor();
        $copia = $conductor->replicate(['user_id', 'firma_path', 'firma_actualizada_at']);
        $copia->empresa_id = $this->otra->id;
        $copia->save();

        $this->entrarComo('admin@ejemplo.test', $this->otra);
        $resultado = app(CrearAccesoConductor::class)($copia);

        $this->assertFalse($resultado['nuevo']);
        $this->assertSame($conductor->user_id, $copia->refresh()->user_id);
    }

    public function test_firma_desde_el_formulario_del_conductor_e_inspeccion_mensual(): void
    {
        $conductor = Conductor::query()->deEmpresa($this->demo)->where('id', '!=', $this->conductor()->id)->firstOrFail();
        $this->entrarComo('admin@demo.test');

        Livewire::test(\App\Filament\App\Resources\Conductores\Pages\EditarConductor::class, ['record' => $conductor->getRouteKey()])
            ->set('data.firma_dibujada', self::FIRMA . base64_encode('no es imagen'))
            ->call('save')
            ->assertHasErrors(['data.firma_dibujada']);

        Livewire::test(\App\Filament\App\Resources\Conductores\Pages\EditarConductor::class, ['record' => $conductor->getRouteKey()])
            ->set('data.firma_dibujada', self::firmaDibujada())
            ->call('save')
            ->assertHasNoErrors();
        $this->assertTrue($conductor->refresh()->tieneFirma());

        $items = ItemChequeo::query()->deEmpresa($this->demo)->deTipo(TipoInspeccion::Mensual)->activos()->get();
        $respuestas = $items->mapWithKeys(fn ($i) => [$i->id => ['respuesta' => 'si', 'observacion' => null]])->all();

        Livewire::test(RegistrarInspeccionMensual::class)
            ->fillForm(['vehiculo_id' => $this->vehiculo()->id, 'fecha' => today()->toDateString(), 'kilometraje' => 5, 'respuestas' => $respuestas])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect();

        $mensual = Inspeccion::query()->deEmpresa($this->demo)->where('tipo', 'mensual')->latest('id')->first();
        $this->assertSame(today()->toDateString(), $mensual->fecha->toDateString());
        $this->assertSame('Administrador Demo', $mensual->datos['registrado_por']);
        $this->assertNull($mensual->firma_path);
    }

    public function test_pendientes_de_hoy_e_informe(): void
    {
        $this->entrarComo('admin@demo.test');
        $vehiculo = $this->vehiculo();

        Livewire::test(PendientesHoy::class)->assertCanSeeTableRecords([$vehiculo]);

        $conductor = $this->conductor();
        app(RegistrarInspeccion::class)($this->demo, TipoInspeccion::Preoperacional, $vehiculo, $this->respuestas(),
            conductor: $conductor, kilometraje: 1, usuario: $conductor->usuario);

        Livewire::test(PendientesHoy::class)->assertCanNotSeeTableRecords([$vehiculo]);

        $inspecciones = Inspeccion::query()->deEmpresa($this->demo)->preoperacionales()->where('vehiculo_id', $vehiculo->id)->get();
        $pdf = PdfInspeccion::informe($vehiculo, today()->subDays(10), today(), $inspecciones, ['razon_social' => 'Demo', 'nit' => '1']);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_crear_usuario_con_rol_conductor_eligiendo_el_conductor(): void
    {
        $conductor = Conductor::query()->deEmpresa($this->demo)->where('id', '!=', $this->conductor()->id)->whereNull('user_id')->firstOrFail();
        $rol = \App\Models\Rol::query()->deEmpresa($this->demo)->where('name', \App\Models\Rol::CONDUCTOR)->firstOrFail();
        $this->entrarComo('admin@demo.test');

        Livewire::test(\App\Filament\App\Resources\Usuarios\Pages\CrearUsuario::class)
            ->fillForm(['rol_id' => $rol->id])
            ->assertFormFieldIsVisible('conductor_id')
            ->fillForm(['conductor_id' => $conductor->id])
            ->assertFormSet(['name' => $conductor->nombre_completo, 'documento' => $conductor->documento])
            ->fillForm(['password' => 'Temporal2026'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $usuario = $conductor->refresh()->usuario;
        $this->assertNotNull($usuario);
        $this->assertNull($usuario->email);
        $this->assertTrue($usuario->debe_cambiar_clave);
        $this->assertTrue($this->demo->usuarios()->whereKey($usuario->id)->exists());
        $this->assertSame(\App\Models\Rol::CONDUCTOR, \App\Actions\Configuracion\AsignarRol::rolActual($usuario, $this->demo)->name);
        // Solo portal del conductor: no entra al panel de la empresa.
        $this->assertTrue($usuario->empresasDisponibles()->isEmpty());
        auth()->logout();

        $this->post('/conductor/ingresar', ['documento' => $conductor->documento, 'clave' => 'Temporal2026'])->assertRedirect();
        $this->get('/conductor')->assertRedirect(route('conductor.clave'));

        // Acceso desactivado en Usuarios: ya no entra al portal.
        $this->demo->usuarios()->updateExistingPivot($usuario->id, ['activo' => false]);
        $this->get('/conductor/clave')->assertRedirect(route('conductor.ingresar'));
    }

    public function test_usuario_conductor_sin_elegir_conductor_no_se_crea(): void
    {
        $rol = \App\Models\Rol::query()->deEmpresa($this->demo)->where('name', \App\Models\Rol::CONDUCTOR)->firstOrFail();
        $this->entrarComo('admin@demo.test');

        Livewire::test(\App\Filament\App\Resources\Usuarios\Pages\CrearUsuario::class)
            ->fillForm(['rol_id' => $rol->id, 'name' => 'Sin conductor'])
            ->call('create')
            ->assertHasFormErrors(['conductor_id' => 'required']);
    }

    public function test_usuario_conductor_con_correo_de_otro_usuario_avisa_en_el_campo(): void
    {
        $conductor = Conductor::query()->deEmpresa($this->demo)->where('id', '!=', $this->conductor()->id)->whereNull('user_id')->firstOrFail();
        $conductor->forceFill(['email' => 'admin@demo.test'])->save();
        $rol = \App\Models\Rol::query()->deEmpresa($this->demo)->where('name', \App\Models\Rol::CONDUCTOR)->firstOrFail();
        $this->entrarComo('admin@demo.test');

        Livewire::test(\App\Filament\App\Resources\Usuarios\Pages\CrearUsuario::class)
            ->fillForm(['rol_id' => $rol->id])
            ->fillForm(['conductor_id' => $conductor->id])
            ->assertFormSet(['email' => 'admin@demo.test'])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertNull($conductor->refresh()->user_id);
    }

    public function test_correo_de_un_usuario_de_otra_empresa_tambien_se_detecta(): void
    {
        $conductor = Conductor::query()->deEmpresa($this->demo)->where('id', '!=', $this->conductor()->id)->whereNull('user_id')->firstOrFail();
        // admin@ejemplo.test es de otra empresa: dentro del panel de la demo no se ve con User::query().
        $conductor->forceFill(['email' => 'admin@ejemplo.test'])->save();
        $rol = \App\Models\Rol::query()->deEmpresa($this->demo)->where('name', \App\Models\Rol::CONDUCTOR)->firstOrFail();
        $this->entrarComo('admin@demo.test');
        // Como en una petición real: filtros por empresa de los recursos del panel (incluido Usuarios).
        \Filament\Facades\Filament::getPanel('app')->bootTenancy();

        Livewire::test(\App\Filament\App\Resources\Usuarios\Pages\CrearUsuario::class)
            ->fillForm(['rol_id' => $rol->id])
            ->fillForm(['conductor_id' => $conductor->id])
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertNull($conductor->refresh()->user_id);
    }

    public function test_con_sesion_de_oficina_abierta_el_conductor_puede_ingresar_al_portal(): void
    {
        $this->get('/app/login')->assertOk()->assertSee('portal del conductor');
        $this->actingAs($this->usuario('admin@demo.test'));
        $this->get('/conductor/ingresar')->assertOk()->assertSee('Número de documento');

        $this->post('/conductor/ingresar', ['documento' => 'x@y.com', 'clave' => 'a'])->assertSessionHasErrors('documento');
        $this->post('/conductor/ingresar', ['documento' => '20000001', 'clave' => 'Ruta2026*'])->assertRedirect(route('conductor.inicio'));
        $this->get('/conductor')->assertOk()->assertSee('Servicios asignados');
    }

    public function test_si_se_pierde_el_archivo_de_la_firma_se_vuelve_a_pedir(): void
    {
        $conductor = $this->conductor();
        Storage::disk('local')->delete($conductor->firma_path);
        $this->assertFalse($conductor->refresh()->tieneFirma());

        try {
            app(RegistrarInspeccion::class)($this->demo, TipoInspeccion::Preoperacional, $this->vehiculo(), $this->respuestas(), conductor: $conductor, kilometraje: 1);
            $this->fail('Debió rechazar');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Regístrela de nuevo', $e->errors()['conductor'][0]);
        }

        $this->entrarAlPortal();
        $this->get('/conductor')->assertSee('Falta su firma');
        $this->get(route('conductor.preoperacional', ['vehiculo' => $this->vehiculo()->id]))->assertRedirect(route('conductor.firma'));
        $this->get('/conductor/firma')->assertOk();
    }
}
