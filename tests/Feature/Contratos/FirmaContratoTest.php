<?php

namespace Tests\Feature\Contratos;

use App\Actions\Contratos\AnularDocumentoContrato;
use App\Actions\Contratos\EmitirDocumentoContrato;
use App\Actions\Contratos\EnviarParaFirma;
use App\Actions\Contratos\GuardarPlantillaContrato;
use App\Enums\EstadoFirma;
use App\Mail\CorreoFirmaContrato;
use App\Models\Contrato;
use App\Models\DocumentoContrato;
use App\Models\Empresa;
use App\Models\EnvioMensaje;
use App\Models\PlantillaContrato;
use App\Support\Contratos\PlantillaBase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class FirmaContratoTest extends ConfiguracionTestCase
{
    /** Mensajes de texto capturados (lo que se habría enviado a Onurix). */
    private array $sms = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->sms = [];
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/send-sms')) {
                $this->sms[] = $request->data();

                return Http::response(['status' => 'success', 'transactionId' => 'TX' . count($this->sms)]);
            }

            return Http::response(['status' => 'success', 'balance' => 1234]);
        });
    }

    private function configurarSms(?int $limite = null): void
    {
        $this->demo->forceFill([
            'sms_habilitado' => true,
            'onurix_cliente' => '9999',
            'onurix_clave' => 'clave-secreta-de-prueba',
            'sms_limite_mensual' => $limite,
        ])->save();
    }

    /** Contrato empresarial demo (tiene todos los datos) con su documento emitido. */
    private function documentoEmitido(): DocumentoContrato
    {
        return DocumentoContrato::query()->deEmpresa($this->demo)->where('estado', 'emitido')->firstOrFail();
    }

    private function enviar(DocumentoContrato $documento): string
    {
        $this->entrarComo('admin@demo.test');

        return app(EnviarParaFirma::class)($documento, [
            'nombre' => 'Jefe de talento humano',
            'documento' => '1234567',
            'celular' => '300 111 2233',
            'email' => 'talento@agro.test',
        ])['enlace'];
    }

    private function token(string $enlace): string
    {
        return basename(parse_url($enlace, PHP_URL_PATH));
    }

    private function firmaPng(): string
    {
        $img = imagecreatetruecolor(600, 200);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imagesetthickness($img, 4);
        $tinta = imagecolorallocate($img, 10, 10, 40);
        for ($i = 0; $i < 12; $i++) {
            imageline($img, 30 + $i * 45, 150 - ($i % 2) * 90, 75 + $i * 45, 150 - (($i + 1) % 2) * 90, $tinta);
        }
        ob_start();
        imagepng($img);

        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }

    private function ultimoCodigoSms(): string
    {
        preg_match('/es (\d{6})\./', end($this->sms)['sms'] ?? '', $m);

        return $m[1] ?? '';
    }

    public function test_pantallas_de_plantilla_integraciones_y_contrato(): void
    {
        $this->actingAs($this->usuario('admin@demo.test'));
        $plantilla = PlantillaContrato::query()->deEmpresa($this->demo)->orderByDesc('version')->first();
        $contrato = $this->documentoEmitido()->contrato;

        foreach ([
            '/app/cootrasec-demo/plantilla-contrato',
            '/app/cootrasec-demo/plantilla-contrato/nueva-version',
            "/app/cootrasec-demo/plantilla-contrato/{$plantilla->id}",
            '/app/cootrasec-demo/integraciones',
            "/app/cootrasec-demo/contratos/{$contrato->id}",
            '/app/cootrasec-demo/profile',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // Emitido: no se edita (redirige a la vista).
        $this->get("/app/cootrasec-demo/contratos/{$contrato->id}/editar")->assertRedirect();
    }

    public function test_permisos(): void
    {
        $this->actingAs($this->usuario('gerencia@demo.test'));
        $this->get('/app/cootrasec-demo/integraciones')->assertForbidden();
        $this->get('/app/cootrasec-demo/plantilla-contrato')->assertOk();
        $this->get('/app/cootrasec-demo/plantilla-contrato/nueva-version')->assertForbidden();

        $this->entrarComo('auxiliar@demo.test');
        $documento = $this->documentoEmitido();
        $this->assertTrue(auth()->user()->can('create', DocumentoContrato::class));
        $this->assertFalse(auth()->user()->can('anular', $documento));
    }

    public function test_versiones_de_la_plantilla(): void
    {
        $this->entrarComo('admin@demo.test');
        $guardar = app(GuardarPlantillaContrato::class);
        $vigente = $this->demo->plantillaContratoVigente();

        $nueva = $guardar($this->demo, ['contenido' => $vigente->contenido . '<p>Cláusula nueva.</p>', 'codigo_formato' => 'GPS-PR01-FT03']);
        $this->assertSame($vigente->version + 1, $nueva->version);
        $this->assertTrue($nueva->esVigente());
        $this->assertFalse($vigente->fresh()->esVigente());

        // Versión repetida y variable inventada: rechazadas.
        $this->assertThrows(fn () => $guardar($this->demo, ['contenido' => '<p>x</p>', 'version' => $nueva->version]), ValidationException::class);
        $this->assertThrows(fn () => $guardar($this->demo, ['contenido' => '<p><span data-type="mergeTag" data-id="cliente.inventado"></span></p>']), ValidationException::class);

        // Cada empresa tiene su plantilla.
        $this->assertNotNull($this->otra->plantillaContratoVigente());
        $this->assertSame(PlantillaBase::contenido(), $this->otra->plantillaContratoVigente()->contenido);
    }

    public function test_emitir_exige_los_datos_y_congela_el_texto(): void
    {
        $this->entrarComo('admin@demo.test');

        // El contrato escolar no tiene horas: no se emite y dice qué falta.
        $escolar = Contrato::query()->deEmpresa($this->demo)->where('numero', 1)->firstOrFail();
        try {
            app(EmitirDocumentoContrato::class)($escolar);
            $this->fail('Debió bloquear la emisión.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Contrato · Hora de inicio', implode(' ', $e->errors()['documento']));
        }

        $documento = $this->documentoEmitido();
        $this->assertStringContainsString('CUATRO MILLONES QUINIENTOS MIL PESOS M/CTE ($ 4.500.000)', $documento->contenido);
        $this->assertStringContainsString('9 horas', $documento->contenido);
        $this->assertStringNotContainsString('data-type="mergeTag"', $documento->contenido);
        $this->assertSame(64, strlen($documento->huella_contenido));

        // Cambiar la plantilla después no altera el documento emitido.
        app(GuardarPlantillaContrato::class)($this->demo, ['contenido' => '<p>Otro texto <span data-type="mergeTag" data-id="contrato.numero"></span></p>']);
        $this->assertStringContainsString('CUATRO MILLONES', $documento->fresh()->contenido);

        // No se emite dos veces mientras haya uno activo.
        $this->assertThrows(fn () => app(EmitirDocumentoContrato::class)($documento->contrato), ValidationException::class);
    }

    public function test_flujo_completo_de_firma_con_sms(): void
    {
        $this->configurarSms();
        $documento = $this->documentoEmitido();
        $enlace = $this->enviar($documento);
        $token = $this->token($enlace);

        // Enlace por SMS (sin tildes, un solo mensaje) y por correo.
        $this->assertCount(1, $this->sms);
        $this->assertSame('3001112233', $this->sms[0]['phone']);
        $this->assertSame('9999', $this->sms[0]['client']);
        $this->assertStringContainsString($enlace, $this->sms[0]['sms']);
        $this->assertLessThanOrEqual(160, strlen($this->sms[0]['sms']));
        Mail::assertSent(CorreoFirmaContrato::class, fn ($m) => $m->tipo === CorreoFirmaContrato::ENLACE && $m->hasTo('talento@agro.test'));
        $this->assertSame(2, EnvioMensaje::query()->deEmpresa($this->demo)->where('estado', 'enviado')->count());

        // Solo la huella del enlace queda en la base.
        $this->assertNotSame($token, DB::table('documentos_contrato')->where('id', $documento->id)->value('token_hash'));

        auth()->logout();

        // Abre el enlace: queda "visto".
        $this->get($enlace)->assertOk()->assertSee('Enviar código')->assertSee('••• ••• 2233');
        $this->assertSame(EstadoFirma::Visto, $documento->fresh()->estado);

        // Pide el código: llega por SMS.
        $this->post(route('contrato.firma.codigo', $token), ['canal' => 'sms'])->assertRedirect();
        $codigo = $this->ultimoCodigoSms();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $codigo);

        // Código equivocado: no firma y descuenta intentos.
        $this->post(route('contrato.firma.firmar', $token), ['codigo' => '000000' === $codigo ? '111111' : '000000', 'firma' => $this->firmaPng(), 'acepto' => 1])
            ->assertSessionHasErrors('codigo');
        $this->assertSame(EstadoFirma::Visto, $documento->fresh()->estado);

        // Sin dibujar la firma: no firma.
        $this->post(route('contrato.firma.firmar', $token), ['codigo' => $codigo, 'firma' => 'data:image/png;base64,' . base64_encode('x'), 'acepto' => 1])
            ->assertSessionHasErrors('firma');

        // Código correcto + firma + aceptación: firmado.
        $this->post(route('contrato.firma.firmar', $token), ['codigo' => $codigo, 'firma' => $this->firmaPng(), 'acepto' => 1])
            ->assertRedirect(route('contrato.firma', $token))
            ->assertSessionHasNoErrors();

        $documento->refresh();
        $this->assertSame(EstadoFirma::Firmado, $documento->estado);
        $this->assertSame('sms', $documento->firma_canal_codigo);
        $this->assertNotNull($documento->firma_ip);
        Storage::disk('local')->assertExists($documento->pdf_firmado_path);
        $pdf = Storage::disk('local')->get($documento->pdf_firmado_path);
        $this->assertSame(hash('sha256', $pdf), $documento->huella_pdf);
        $this->assertStringStartsWith('%PDF', $pdf);

        // Copia por correo con el PDF adjunto.
        Mail::assertSent(CorreoFirmaContrato::class, fn ($m) => $m->tipo === CorreoFirmaContrato::FIRMADO && $m->pdf !== null);

        // El enlace ya no permite firmar; sí descargar la copia firmada.
        $this->get($enlace)->assertOk()->assertSee('Firmado')->assertDontSee('Enviar código');
        $this->get(route('contrato.firma.pdf', $token))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->post(route('contrato.firma.codigo', $token))->assertSessionHasErrors('codigo');

        // Verificación pública: estado y comparación del archivo.
        $this->get($documento->urlVerificacion())->assertOk()->assertSee('Firmado')->assertSee($documento->huella_pdf);
        $this->post(route('contrato.verificar.archivo', $documento->codigo_verificacion), [
            'archivo' => UploadedFile::fake()->createWithContent('contrato.pdf', $pdf),
        ])->assertOk()->assertSee('Coincide');
        $this->post(route('contrato.verificar.archivo', $documento->codigo_verificacion), [
            'archivo' => UploadedFile::fake()->createWithContent('contrato.pdf', $pdf . '%alterado'),
        ])->assertOk()->assertSee('No coincide');

        // Evidencias.
        $eventos = $documento->eventos()->pluck('evento')->all();
        foreach (['emitido', 'enviado', 'visto', 'codigo_enviado', 'codigo_fallido', 'firmado'] as $evento) {
            $this->assertContains($evento, $eventos);
        }
    }

    public function test_limites_del_codigo(): void
    {
        $this->configurarSms();
        $documento = $this->documentoEmitido();
        $token = $this->token($this->enviar($documento));
        auth()->logout();

        // Máximo 3 códigos cada 15 minutos.
        foreach (range(1, 3) as $i) {
            $this->post(route('contrato.firma.codigo', $token), ['canal' => 'sms'])->assertSessionHasNoErrors();
        }
        $this->post(route('contrato.firma.codigo', $token), ['canal' => 'sms'])->assertSessionHasErrors('codigo');
        $this->assertCount(4, $this->sms, '1 enlace + 3 códigos; el cuarto no se envía.');

        // 5 intentos fallidos bloquean el código, aunque después se escriba bien.
        $codigo = $this->ultimoCodigoSms();
        $malo = $codigo === '123456' ? '654321' : '123456';
        foreach (range(1, 5) as $i) {
            $this->post(route('contrato.firma.firmar', $token), ['codigo' => $malo, 'firma' => $this->firmaPng(), 'acepto' => 1]);
        }
        $this->post(route('contrato.firma.firmar', $token), ['codigo' => $codigo, 'firma' => $this->firmaPng(), 'acepto' => 1])
            ->assertSessionHasErrors('codigo');
        $this->assertNotSame(EstadoFirma::Firmado, $documento->fresh()->estado);

        // El código vence a los 10 minutos.
        $this->travel(16)->minutes();
        $this->post(route('contrato.firma.codigo', $token), ['canal' => 'sms'])->assertSessionHasNoErrors();
        $codigo = $this->ultimoCodigoSms();
        $this->travel(11)->minutes();
        $this->post(route('contrato.firma.firmar', $token), ['codigo' => $codigo, 'firma' => $this->firmaPng(), 'acepto' => 1])
            ->assertSessionHasErrors('codigo');
    }

    public function test_sin_sms_el_codigo_va_por_correo_y_respeta_el_limite_mensual(): void
    {
        $documento = $this->documentoEmitido();
        $token = $this->token($this->enviar($documento)); // sin SMS configurado: solo correo
        auth()->logout();

        $this->assertCount(0, $this->sms);
        $this->assertSame('omitido', EnvioMensaje::query()->deEmpresa($this->demo)->where('canal', 'sms')->value('estado'));

        $this->post(route('contrato.firma.codigo', $token))->assertSessionHasNoErrors();
        Mail::assertSent(CorreoFirmaContrato::class, fn ($m) => $m->tipo === CorreoFirmaContrato::CODIGO && preg_match('/^\d{6}$/', (string) $m->codigo));

        // Con límite mensual alcanzado, el SMS se omite y se usa el correo.
        $this->configurarSms(limite: 1);
        $this->post(route('contrato.firma.codigo', $token), ['canal' => 'sms'])->assertSessionHasNoErrors(); // usa el único SMS
        $this->post(route('contrato.firma.codigo', $token), ['canal' => 'sms'])->assertSessionHasNoErrors(); // ya no hay SMS: correo
        $this->assertCount(1, $this->sms);
    }

    public function test_enlace_vencido_anulado_y_seguimiento(): void
    {
        $documento = $this->documentoEmitido();
        $documento->forceFill(['user_id' => $this->usuario('admin@demo.test')->id])->save(); // quien emitió recibe los avisos
        $enlace = $this->enviar($documento);
        $token = $this->token($enlace);

        // Recordatorio después de los días configurados (2), con el mismo enlace.
        $this->travel(3)->days();
        $this->artisan('ruta:firmas')->assertSuccessful();
        $this->assertNotNull($documento->fresh()->recordatorio_enviado_at);
        Mail::assertSent(CorreoFirmaContrato::class, fn ($m) => $m->tipo === CorreoFirmaContrato::RECORDATORIO && $m->enlace === $enlace);

        // Vence (7 días): no permite firmar y avisa a quien emitió.
        $this->travel(6)->days();
        auth()->logout();
        $this->get($enlace)->assertOk()->assertSee('Enlace vencido');
        $this->post(route('contrato.firma.codigo', $token))->assertSessionHasErrors('codigo');
        $this->artisan('ruta:firmas')->assertSuccessful();
        $this->assertNotNull($documento->fresh()->vencimiento_avisado_at);
        $this->assertSame(1, $this->usuario('admin@demo.test')->notifications()->where('data', 'like', '%enlace de firma%')->count());

        // Reenviar renueva el enlace.
        $this->entrarComo('admin@demo.test');
        $nuevo = app(EnviarParaFirma::class)($documento->fresh(), ['nombre' => 'X', 'email' => 'talento@agro.test'])['enlace'];
        $this->assertNotSame($enlace, $nuevo);
        $this->assertSame(404, $this->get($enlace)->status());

        // Anulado: el enlace deja de existir y se puede emitir de nuevo.
        app(AnularDocumentoContrato::class)($documento->fresh(), 'Error en el valor');
        $this->get($nuevo)->assertNotFound();
        $this->get($documento->urlVerificacion())->assertOk()->assertSee('Anulado');
        $this->assertSame(EstadoFirma::Emitido, app(EmitirDocumentoContrato::class)($documento->contrato->fresh())->estado);
    }

    public function test_credenciales_cifradas_y_fuera_de_la_auditoria(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->configurarSms();

        $crudo = DB::table('empresas')->where('id', $this->demo->id)->value('onurix_clave');
        $this->assertNotSame('clave-secreta-de-prueba', $crudo);
        $this->assertSame('clave-secreta-de-prueba', Empresa::find($this->demo->id)->onurix_clave);

        $auditoria = Activity::query()->where('subject_type', Empresa::class)->get()->toJson();
        $this->assertStringNotContainsString('clave-secreta-de-prueba', $auditoria);
        $this->assertStringNotContainsString('onurix_clave', $auditoria);
        $this->assertArrayNotHasKey('onurix_clave', Empresa::find($this->demo->id)->toArray());
    }
}
