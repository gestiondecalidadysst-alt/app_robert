<?php

namespace Tests\Feature\Carga;

use App\Enums\ModuloEmpresa;
use App\Jobs\ReintentarEnvioRndc;
use App\Models\Municipio;
use App\Models\RegistroRndc;
use App\Models\TerceroCarga;
use App\Models\TipoIdentificacion;
use App\Services\Rndc\Rndc;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Contingencia: cuando el RNDC está caído el envío queda en espera y se reintenta solo.
 *
 * Lo importante es la distinción: una caída del servicio se reintenta; un rechazo de
 * datos no, porque repetirlo da el mismo error y hay que corregir el documento.
 */
class ContingenciaRndcTest extends ConfiguracionTestCase
{
    private function empresaConectada(): void
    {
        $this->demo->update([
            'modulos' => [...ModuloEmpresa::porDefecto(), 'carga'],
            'rndc_conectado' => true, 'rndc_usuario' => 'USUARIO1', 'rndc_clave' => 'CLAVE1',
            'rndc_ambiente' => 'pruebas',
        ]);
        $this->entrarComo('admin@demo.test');
        Queue::fake();
    }

    private function tercero(): TerceroCarga
    {
        return TerceroCarga::create(['empresa_id' => $this->demo->id,
            'tipo_identificacion_id' => TipoIdentificacion::where('codigo', 'CC')->value('id'),
            'documento' => '5511223', 'nombre' => 'Tercero', 'primer_apellido' => 'Prueba',
            'celular' => '3001234567', 'direccion' => 'Calle 1',
            'municipio_id' => Municipio::where('codigo_dane', '23001')->value('id')]);
    }

    public function test_una_caida_del_rndc_deja_el_envio_en_contingencia_y_programa_el_reintento(): void
    {
        $this->empresaConectada();
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        $registro = app(Rndc::class)->registrar($this->tercero());

        $this->assertSame('contingencia', $registro->estado);
        $this->assertNotNull($registro->reintentar_a);
    }

    public function test_un_rechazo_de_datos_no_se_reintenta(): void
    {
        $this->empresaConectada();
        Http::fake(['*' => Http::response('<root><ErrorMSG>TER020 El número de identificación es inválido</ErrorMSG></root>')]);

        $registro = app(Rndc::class)->registrar($this->tercero());

        $this->assertSame('error', $registro->estado);
        $this->assertNull($registro->reintentar_a);
        $this->assertSame(0, (int) $registro->intentos);
    }

    public function test_el_reintento_radica_cuando_el_rndc_vuelve(): void
    {
        $this->empresaConectada();
        // Http::fake() repetido no reemplaza al primero: hay que usar una secuencia.
        Http::fakeSequence()->push('', 503)->push('<root><ingresoid>4321</ingresoid></root>');

        $registro = app(Rndc::class)->registrar($this->tercero());
        $this->assertSame('contingencia', $registro->estado);

        (new ReintentarEnvioRndc($registro->id))->handle();

        $registro->refresh();
        $this->assertSame('registrado', $registro->estado);
        $this->assertSame('4321', $registro->ingresoid);
        $this->assertNull($registro->reintentar_a);
        $this->assertSame(1, $registro->intentos);
    }

    public function test_si_al_reintentar_el_rndc_rechaza_los_datos_deja_de_reintentar(): void
    {
        $this->empresaConectada();
        Http::fakeSequence()->push('', 503)->push('<root><ErrorMSG>TER030 No reportó el nombre</ErrorMSG></root>');

        $registro = app(Rndc::class)->registrar($this->tercero());
        (new ReintentarEnvioRndc($registro->id))->handle();

        $registro->refresh();
        $this->assertSame('error', $registro->estado);
        $this->assertNull($registro->reintentar_a);
    }

    public function test_tras_agotar_las_esperas_pide_radicar_en_la_web(): void
    {
        $this->empresaConectada();
        Http::fake(['*' => Http::response('', 503)]);
        $registro = app(Rndc::class)->registrar($this->tercero());

        $registro->update(['intentos' => count(ReintentarEnvioRndc::ESPERAS) - 1]);
        (new ReintentarEnvioRndc($registro->id))->handle();

        $registro->refresh();
        $this->assertSame('error', $registro->estado);
        $this->assertStringContainsString('web del RNDC', $registro->errores[0]['mensaje']);
    }

    public function test_la_tarea_programada_reencola_lo_que_quedo_esperando(): void
    {
        $this->empresaConectada();
        Http::fake(['*' => Http::response('', 503)]);
        $registro = app(Rndc::class)->registrar($this->tercero());
        $registro->update(['reintentar_a' => now()->subHour()]);

        $this->artisan('ruta:rndc-contingencia')->assertSuccessful();

        Queue::assertPushed(ReintentarEnvioRndc::class);
    }

    public function test_el_estado_del_documento_avisa_que_esta_en_espera(): void
    {
        $this->empresaConectada();
        Http::fake(['*' => Http::response('', 503)]);
        $tercero = $this->tercero();
        app(Rndc::class)->registrar($tercero);

        $this->assertSame('contingencia', $tercero->fresh()->estadoRndc());
        $this->assertStringContainsString('no respondió', TerceroCarga::etiquetaEstadoRndc('contingencia'));
    }

    public function test_la_bitacora_distingue_la_contingencia(): void
    {
        $this->empresaConectada();
        Http::fake(['*' => Http::response('', 503)]);
        app(Rndc::class)->registrar($this->tercero());

        $registro = RegistroRndc::query()->enContingencia()->firstOrFail();

        $this->assertStringContainsString('contingencia', mb_strtolower($registro->etiquetaEstado()));
    }
}
