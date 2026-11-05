<?php

namespace Tests\Feature\Carga;

use App\Enums\ModuloEmpresa;
use App\Models\Conductor;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\RemesaCarga;
use App\Models\TerceroCarga;
use App\Models\TipoIdentificacion;
use App\Models\Vehiculo;
use App\Services\Rndc\ClienteRndc;
use App\Services\Rndc\Rndc;
use App\Support\Pdf\PdfManifiestoCarga;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class RemesasManifiestosTest extends ConfiguracionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->demo->update(['modulos' => [...ModuloEmpresa::porDefecto(), ModuloEmpresa::Carga->value]]);
    }

    private function tercero(string $doc, int $municipioDane = 23001): TerceroCarga
    {
        $t = new TerceroCarga([
            'tipo_identificacion_id' => TipoIdentificacion::where('codigo', 'CC')->value('id'),
            'documento' => $doc, 'nombre' => "Tercero {$doc}", 'primer_apellido' => 'Demo', 'segundo_apellido' => 'Prueba',
            'celular' => '3001234567', 'direccion' => 'Calle 1', 'municipio_id' => Municipio::where('codigo_dane', $municipioDane)->value('id'),
        ]);
        $t->empresa_id = $this->demo->id;
        $t->save();
        app(Rndc::class)->marcarManual($t, '100' . $doc);

        return $t;
    }

    private function remesa(array $datos = []): RemesaCarga
    {
        static $i = 0; $i++;
        $remitente = $this->tercero(str_pad((string) (1000000 + $i * 10 + 1), 5, '0', STR_PAD_LEFT), 23001);
        $destinatario = $this->tercero(str_pad((string) (1000000 + $i * 10 + 2), 5, '0', STR_PAD_LEFT), 11001);
        $r = new RemesaCarga([
            'fecha' => today(), 'operacion' => 'G', 'empaque_codigo' => '6', 'naturaleza' => 1,
            'producto_codigo' => '001504', 'producto_descripcion' => 'ACEITE DE BACALAO',
            'cantidad' => 20000, 'unidad' => 1,
            'remitente_id' => $remitente->id, 'destinatario_id' => $destinatario->id,
            'dueno_poliza' => 'N', 'horas_pacto_cargue' => 2, 'minutos_pacto_cargue' => 10,
            'horas_pacto_descargue' => 2, 'minutos_pacto_descargue' => 10, ...$datos,
        ]);
        $r->empresa_id = $this->demo->id;
        $r->save();
        app(Rndc::class)->marcarManual($r, '200' . $r->id);

        return $r;
    }

    private function manifiesto(?RemesaCarga $remesa = null): ManifiestoCarga
    {
        $remesa ??= $this->remesa();
        $vehiculo = Vehiculo::query()->deEmpresa($this->demo)->where('placa', 'DMO101')->firstOrFail();
        $vehiculo->update(['rndc_configuracion' => 50, 'peso_vacio' => 4000, 'capacidad_kg' => 8000,
            'rndc_marca' => '1', 'rndc_linea' => '373', 'rndc_color' => '9439', 'rndc_carroceria' => '2', 'rndc_combustible' => 1]);
        app(Rndc::class)->marcarManual($vehiculo->refresh(), '300' . $vehiculo->id);
        $conductor = Conductor::query()->deEmpresa($this->demo)->where('documento', '20000001')->firstOrFail();
        app(Rndc::class)->marcarManual($conductor, '400' . $conductor->id);
        $propietario = Propietario::query()->deEmpresa($this->demo)->firstOrFail();
        app(Rndc::class)->marcarManual($propietario, '500' . $propietario->id);

        $m = new ManifiestoCarga([
            'tipo' => 'G', 'fecha_expedicion' => today(),
            'origen_id' => Municipio::where('codigo_dane', '76001')->value('id'),
            'destino_id' => Municipio::where('codigo_dane', '11001')->value('id'),
            'vehiculo_id' => $vehiculo->id, 'conductor_id' => $conductor->id,
            'titular_propietario_id' => $propietario->id,
            'valor_flete' => 3200000, 'anticipo' => 1000000, 'retencion_fuente_pct' => 1, 'reteica_por_mil' => 3,
            'responsable_cargue' => 'E', 'responsable_descargue' => 'E',
        ]);
        $m->empresa_id = $this->demo->id;
        $m->save();
        $remesa->update(['manifiesto_id' => $m->id]);

        return $m->refresh();
    }

    public function test_consecutivo_automatico_por_empresa(): void
    {
        $this->entrarComo('admin@demo.test');
        $r1 = $this->remesa();
        $r2 = $this->remesa();
        $this->assertSame($r1->numero + 1, $r2->numero);
        $this->assertStringStartsWith('RT-', $r1->numeroFormateado());
    }

    public function test_variables_rndc_de_remesa_siguen_el_diccionario(): void
    {
        $this->entrarComo('admin@demo.test');
        $r = $this->remesa();
        $v = $r->variablesRndc($this->demo);
        $this->assertSame('G', $v['CODOPERACIONTRANSPORTE']);
        $this->assertSame('ACEITE DE BACALAO', $v['DESCRIPCIONCORTAPRODUCTO']);
        $this->assertSame('001504', $v['MERCANCIAREMESA']);
        $this->assertSame(20000, $v['CANTIDADCARGADA']);
        $this->assertSame('N', $v['DUENOPOLIZA']);
        $this->assertArrayNotHasKey('NUMPOLIZATRANSPORTE', $v);
    }

    public function test_validaciones_de_remesa(): void
    {
        $this->entrarComo('admin@demo.test');
        // Mismo municipio en remitente y destinatario: no se admite salvo desechos peligrosos o V.
        $r = new RemesaCarga([
            'fecha' => today(), 'operacion' => 'G', 'empaque_codigo' => '6', 'naturaleza' => 1,
            'producto_codigo' => '001504', 'producto_descripcion' => 'X', 'cantidad' => 100, 'unidad' => 1,
            'remitente_id' => $this->tercero('99901', 23001)->id,
            'destinatario_id' => $this->tercero('99902', 23001)->id,
            'dueno_poliza' => 'E', // falta número, vencimiento y NIT
            'horas_pacto_descargue' => 12, 'minutos_pacto_descargue' => 0,
        ]);
        $r->empresa_id = $this->demo->id;
        $r->save();
        $errores = $r->validarRndc();
        $this->assertStringContainsString('mismo municipio', implode(' ', $errores));
        $this->assertStringContainsString('póliza', implode(' ', $errores));
    }

    public function test_manifiesto_variables_calcula_saldo_y_arma_grupo_remesasman(): void
    {
        $this->entrarComo('admin@demo.test');
        $m = $this->manifiesto();
        $v = $m->variablesRndc($this->demo);

        $this->assertSame('4', (string) $m->procesoRndc());
        $this->assertStringStartsWith('MC-', $m->numeroFormateado());
        $this->assertSame('G', $v['CODOPERACIONTRANSPORTE']);
        $this->assertSame(3200000, $v['VALORFLETEPACTADOVIAJE']);
        $this->assertSame(1000000, $v['VALORANTICIPOMANIFIESTO']);
        $this->assertSame('76001000', $v['CODMUNICIPIOORIGENMANIFIESTO']);
        $this->assertSame('11001000', $v['CODMUNICIPIODESTMANIFIESTO']);
        $this->assertArrayHasKey('REMESASMAN', $v);
        $this->assertSame(43, $v['REMESASMAN']['@procesoid']);
        $this->assertCount(1, $v['REMESASMAN']['items']);

        // Flete 3.200.000 - anticipo 1.000.000 - retefuente 1% (32.000) - reteica 3x1000 (9.600) = 2.158.400.
        $this->assertSame(2158400, $m->saldo());

        $xml = ClienteRndc::request('U', 'P', 1, 4, $v);
        $this->assertStringContainsString('<REMESASMAN procesoid="43">', $xml);
        $this->assertStringContainsString('<CONSECUTIVOREMESA>', $xml);
    }

    public function test_sicetac_bloquea_flete_por_debajo_del_minimo(): void
    {
        $this->entrarComo('admin@demo.test');
        $m = $this->manifiesto();
        $m->update(['valor_minimo_sicetac' => 5000000]);
        $errores = $m->refresh()->validarRndc();
        $this->assertStringContainsString('SICE-TAC', implode(' ', $errores));
    }

    public function test_pdf_del_manifiesto_con_remesas_y_qr(): void
    {
        $this->entrarComo('admin@demo.test');
        $m = $this->manifiesto();
        $pdf = PdfManifiestoCarga::pdf($m);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringStartsWith('manifiesto-MC-', PdfManifiestoCarga::nombre($m));
        // Verificación pública
        $this->get($m->urlVerificacion())->assertOk()->assertSee($m->numeroFormateado())->assertSee('Vigente');
        $this->get($m->urlPdf())->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pantallas_y_permisos(): void
    {
        $m = $this->manifiesto();
        $this->actingAs($this->usuario('admin@demo.test'));
        $this->get('/app/cootrasec-demo/remesas-carga')->assertOk()->assertSee('RT-');
        $this->get('/app/cootrasec-demo/manifiestos-carga')->assertOk()->assertSee('MC-');
        $this->get("/app/cootrasec-demo/manifiestos-carga/{$m->id}/editar")->assertOk();

        // Anulación: solo con permiso.
        $this->flushSession();
        $this->actingAs($this->usuario('gerencia@demo.test'));
        $this->get('/app/cootrasec-demo/manifiestos-carga')->assertOk();
        $this->assertFalse($this->usuario('gerencia@demo.test')->can('anular_manifiestos_carga'));

        // Sin módulo de carga, no abre.
        $this->demo->update(['modulos' => ModuloEmpresa::porDefecto()]);
        $this->flushSession();
        $this->actingAs($this->usuario('admin@demo.test'));
        $this->get('/app/cootrasec-demo/manifiestos-carga')->assertForbidden();
    }

    public function test_envio_al_rndc_con_remesasman(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->demo->forceFill(['rndc_usuario' => 'USUARIODEMO', 'rndc_clave' => 'xyz', 'rndc_conectado' => true])->save();
        $m = $this->manifiesto();

        Http::fake(['*' => Http::response('<?xml version="1.0"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body>'
            . '<NS1:AtenderMensajeRNDCResponse xmlns:NS1="urn:BPMServicesIntf-IBPMServices"><return>'
            . htmlspecialchars('<root><ingresoid>999888</ingresoid></root>', ENT_XML1) . '</return></NS1:AtenderMensajeRNDCResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>')]);

        $registro = app(Rndc::class)->registrar($m);
        $this->assertSame('registrado', $registro->estado);
        $this->assertSame('999888', $registro->ingresoid);
        Http::assertSent(fn ($req) => str_contains($req->body(), 'REMESASMAN'));
    }
}
