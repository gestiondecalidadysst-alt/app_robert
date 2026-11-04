<?php

namespace Tests\Feature\Carga;

use App\Enums\ModuloEmpresa;
use App\Filament\App\Resources\TercerosCarga\Pages\GestionarTercerosCarga;
use App\Models\Conductor;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\RegistroRndc;
use App\Models\Remolque;
use App\Models\TerceroCarga;
use App\Models\TipoIdentificacion;
use App\Models\Vehiculo;
use App\Services\Rndc\ClienteRndc;
use App\Services\Rndc\RespuestaRndc;
use App\Services\Rndc\Rndc;
use App\Support\Pdf\PdfFichaRndc;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class CargaRndcTest extends ConfiguracionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->demo->update(['modulos' => [...ModuloEmpresa::porDefecto(), ModuloEmpresa::Carga->value]]);
    }

    private function tercero(array $datos = []): TerceroCarga
    {
        $t = new TerceroCarga([
            'tipo_identificacion_id' => TipoIdentificacion::where('codigo', 'CC')->value('id'),
            'documento' => '1.234.567', 'nombre' => 'Remitente', 'primer_apellido' => 'Prueba', 'segundo_apellido' => 'Demo',
            'celular' => '3001234567', 'direccion' => 'Calle 1 # 2-3', 'municipio_id' => Municipio::where('codigo_dane', '23001')->value('id'),
            'roles' => ['remitente'], ...$datos,
        ]);
        $t->empresa_id = $this->demo->id;
        $t->save();

        return $t;
    }

    private function conectar(): void
    {
        $this->demo->forceFill(['rndc_usuario' => 'USUARIODEMO', 'rndc_clave' => 'clave-secreta', 'rndc_conectado' => true])->save();
    }

    private static function soap(string $interno): string
    {
        return '<?xml version="1.0"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body>'
            . '<NS1:AtenderMensajeRNDCResponse xmlns:NS1="urn:BPMServicesIntf-IBPMServices"><return>' . htmlspecialchars($interno, ENT_XML1)
            . '</return></NS1:AtenderMensajeRNDCResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>';
    }

    public function test_variables_y_validaciones_de_terceros(): void
    {
        $this->entrarComo('admin@demo.test');
        $t = $this->tercero();

        $v = $t->variablesRndc($this->demo);
        $this->assertSame('C', $v['CODTIPOIDTERCERO']);
        $this->assertSame('1234567', $v['NUMIDTERCERO']);
        $this->assertSame('PRUEBA', $v['PRIMERAPELLIDOIDTERCERO']);
        $this->assertSame('23001000', $v['CODMUNICIPIORNDC'], 'Código DANE + 000.');
        $this->assertArrayNotHasKey('CODSEDETERCERO', $v);
        $this->assertSame([], $t->validarRndc());

        // Empresa: sede y teléfono fijo obligatorio; sin apellidos.
        $nit = $this->tercero(['tipo_identificacion_id' => TipoIdentificacion::where('codigo', 'NIT')->value('id'), 'documento' => '9001234567',
            'nombre' => 'Destino Demo SAS', 'primer_apellido' => 'X', 'telefono' => null, 'sede_codigo' => '2', 'sede_nombre' => 'bodega']);
        $vn = $nit->variablesRndc($this->demo);
        $this->assertSame('N', $vn['CODTIPOIDTERCERO']);
        $this->assertSame(['2', 'BODEGA'], [$vn['CODSEDETERCERO'], $vn['NOMSEDETERCERO']]);
        $this->assertArrayNotHasKey('PRIMERAPELLIDOIDTERCERO', $vn);
        $this->assertStringContainsString('teléfono fijo', implode(' ', $nit->validarRndc()));

        // Conductor: con su licencia; el vehículo exige registrar antes al propietario.
        $conductor = Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->firstOrFail();
        $this->assertArrayHasKey('NUMLICENCIACONDUCCION', $conductor->variablesRndc($this->demo));
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->firstOrFail();
        $vehiculo->update(['rndc_configuracion' => 50, 'peso_vacio' => 4000, 'capacidad_kg' => 8000, 'rndc_marca' => '1', 'rndc_linea' => '373',
            'rndc_color' => '9439', 'rndc_carroceria' => '2', 'rndc_combustible' => 1]);
        $this->assertSame(50, $vehiculo->variablesRndc($this->demo)['CODCONFIGURACIONUNIDADCARGA']);
        $this->assertStringContainsString('Registre primero en el RNDC al propietario', implode(' ', Rndc::pendientes($vehiculo->refresh())));
    }

    public function test_registro_por_web_service_con_radicado_o_errores(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->conectar();
        $t = $this->tercero();

        Http::fake(['*' => Http::sequence()
            ->push(self::soap('<?xml version="1.0" encoding="ISO-8859-1" ?><root><ingresoid>123456</ingresoid></root>'))
            ->push(self::soap('<root><ErrorMSG>Error TER020: El número de identificación del Tercero debe ser de más de 5 caracteres</ErrorMSG></root>'))]);
        $r = app(Rndc::class)->registrar($t);
        $this->assertSame('registrado', $r->estado);
        $this->assertSame('123456', $r->ingresoid);
        $this->assertSame('registrado', $t->refresh()->estadoRndc());
        Http::assertSent(fn ($req) => str_contains($req->body(), 'AtenderMensajeRNDC') && str_contains($req->body(), '&lt;procesoid&gt;11')
            && str_contains($req->header('SOAPAction')[0], 'AtenderMensajeRNDC'));
        $this->assertStringNotContainsString('clave-secreta', json_encode($r->toArray()), 'La clave no queda en la bitácora.');

        // Rechazo: errores con su código; el tercero queda con errores.
        $mal = app(Rndc::class)->registrar($t);
        $this->assertSame('error', $mal->estado);
        $this->assertSame('TER020', $mal->errores[0]['codigo']);
        $this->assertSame('error', $t->refresh()->estadoRndc());

        // Lo que no cumple el diccionario no se envía.
        $t->update(['direccion' => '']);
        $this->assertThrows(fn () => app(Rndc::class)->registrar($t->refresh()), ValidationException::class);
        $this->assertSame(2, RegistroRndc::count());
    }

    public function test_sin_conexion_se_anota_el_radicado_de_la_web(): void
    {
        $this->entrarComo('admin@demo.test');
        $t = $this->tercero();
        Http::fake();

        $this->assertThrows(fn () => app(Rndc::class)->registrar($t), ValidationException::class, 'no está activa');
        Http::assertNothingSent();

        app(Rndc::class)->marcarManual($t, '987654');
        $this->assertSame('registrado', $t->refresh()->estadoRndc());
        $this->travel(1)->minutes();
        $t->update(['celular' => '3009998877']);
        $this->assertSame('desactualizado', $t->refresh()->estadoRndc(), 'Si cambia después, hay que volver a registrarlo.');

        // Vehículo con propietario registrado: ya no lo bloquea el propietario.
        $propietario = Propietario::query()->deEmpresa($this->demo)->firstOrFail();
        app(Rndc::class)->marcarManual($propietario, '555');
        $remolque = new Remolque(['placa' => 'r12345', 'configuracion' => 63, 'marca_codigo' => '9', 'modelo' => 2020, 'peso_vacio' => 9000,
            'unidad_capacidad' => 1, 'propietario_id' => $propietario->id]);
        $remolque->empresa_id = $this->demo->id;
        $remolque->save();
        $this->assertSame('R12345', $remolque->placa);
        $this->assertSame([], Rndc::pendientes($remolque));
        $this->assertSame('63', (string) $remolque->variablesRndc($this->demo)['CODCONFIGURACIONUNIDADCARGA']);
    }

    public function test_respuesta_del_rndc(): void
    {
        $ok = RespuestaRndc::desdeXml('<root><ingresoid>12</ingresoid></root>');
        $this->assertTrue($ok->ok());
        $mal = RespuestaRndc::desdeXml('<root><ErrorMSG>VEH010: La placa no existe en el RUNT</ErrorMSG></root>');
        $this->assertFalse($mal->ok());
        $this->assertSame('VEH010', $mal->errores[0]['codigo']);
        $xml = ClienteRndc::request('U', 'P', 1, 11, ['NOMIDTERCERO' => 'A & B']);
        $this->assertStringContainsString('<NOMIDTERCERO>A &amp; B</NOMIDTERCERO>', $xml);
        $this->assertStringContainsString("encoding='ISO-8859-1'", $xml);
    }

    public function test_pantallas_ficha_pdf_e_integraciones(): void
    {
        $t = $this->tercero();
        $this->actingAs($this->usuario('admin@demo.test'));
        $this->get('/app/cootrasec-demo/terceros-carga')->assertOk()->assertSee('Remitente Prueba Demo')->assertSee('Sin registrar');
        $this->get('/app/cootrasec-demo/remolques')->assertOk();
        $this->get('/app/cootrasec-demo/bitacora-rndc')->assertOk();
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->firstOrFail();
        $this->get("/app/cootrasec-demo/vehiculos/{$vehiculo->id}/editar")->assertOk()->assertSee('Carga (RNDC)');
        $this->get("/app/cootrasec-demo/vehiculos/{$vehiculo->id}")->assertOk();

        $this->assertStringStartsWith('%PDF', PdfFichaRndc::fichas([$t]));
        $html = view('pdf.ficha-rndc', ['empresa' => $this->demo, 'logo' => null, 'conectado' => false, 'fichas' => collect([[
            'titulo' => $t->etiquetaRndc(), 'proceso' => 11, 'variables' => $t->variablesRndc($this->demo), 'pendientes' => [], 'estado' => 'pendiente', 'ultimo' => null,
        ]])])->render();
        $this->assertStringContainsString('NOMENCLATURADIRECCION', $html);
        $this->assertStringContainsString('anote en el sistema el número de radicado', $html);

        $this->entrarComo('admin@demo.test');
        Livewire::test(GestionarTercerosCarga::class)
            ->callTableAction('rndc_ficha', $t)->assertFileDownloaded()
            ->assertTableActionHidden('rndc_registrar', $t)
            ->callTableAction('rndc_manual', $t, ['ingresoid' => '4321'])->assertHasNoTableActionErrors();
        $this->assertSame('4321', $t->ultimoRegistroRndc()->first()->ingresoid);

        // Credenciales cifradas; la conexión exige usuario y clave.
        Livewire::test(\App\Filament\App\Pages\Integraciones::class)
            ->assertSee('RNDC')
            ->set('data.rndc_conectado', true)->call('guardar');
        $this->assertFalse($this->demo->refresh()->rndc_conectado);
        Livewire::test(\App\Filament\App\Pages\Integraciones::class)
            ->set('data.rndc_usuario', 'USUARIODEMO')->set('data.rndc_clave', 'clave-secreta')->set('data.rndc_conectado', true)->call('guardar');
        $this->demo->refresh();
        $this->assertTrue($this->demo->rndc_conectado);
        $this->assertSame('clave-secreta', $this->demo->rndc_clave);
        $this->assertStringNotContainsString('clave-secreta', (string) DB::table('empresas')->where('id', $this->demo->id)->value('rndc_clave'));

        // El auxiliar tiene el módulo; sin el módulo, nada.
        $this->flushSession();
        $this->actingAs($this->usuario('auxiliar@demo.test'));
        $this->get('/app/cootrasec-demo/terceros-carga')->assertOk();
        $this->demo->update(['modulos' => ModuloEmpresa::porDefecto()]);
        $this->get('/app/cootrasec-demo/terceros-carga')->assertForbidden();
        $this->get('/app/cootrasec-demo/bitacora-rndc')->assertForbidden();
    }
}
