<?php

namespace Tests\Feature\Sistema;

use App\Filament\Admin\Pages\EstadoSistema;
use App\Jobs\EnviarCorreo;
use App\Jobs\EnviarSms;
use App\Jobs\EnviarWebhook;
use App\Models\ErrorSistema;
use App\Models\User;
use App\Support\Sistema\Colas;
use App\Support\Sistema\Latidos;
use App\Support\Sistema\Referencia;
use App\Support\Sistema\RegistroErrores;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

class SistemaTest extends ConfiguracionTestCase
{
    private function superadmin(): User
    {
        return $this->usuario('superadmin@ruta.test');
    }

    private function avisos(): int
    {
        return $this->superadmin()->notifications()->count();
    }

    public function test_cada_trabajo_va_a_su_fila_y_los_webhooks_van_cifrados(): void
    {
        $this->assertSame(Colas::ALTA, (new EnviarSms(1, '3000000000', 'x'))->queue);
        $this->assertSame(Colas::ALTA, (new EnviarCorreo(1, 'a@b.test', new \App\Mail\CorreoInformeGerencia('E', 'D', 'P', '', 'x.pdf')))->queue);
        $this->assertSame(Colas::BAJA, (new EnviarWebhook(1))->queue);
        $this->assertInstanceOf(ShouldBeEncrypted::class, new EnviarWebhook(1));
        $this->assertSame(['alta', 'default', 'baja'], Colas::ORDEN);
    }

    public function test_errores_agrupados_con_referencia_y_alerta_una_vez_por_hora(): void
    {
        $antes = $this->avisos();
        $falla = fn (int $id) => new RuntimeException("No se encontró el vehículo {$id}");

        $e1 = RegistroErrores::registrar($falla(15));
        Referencia::reiniciar();
        $e2 = RegistroErrores::registrar($falla(16));

        $this->assertSame($e1->id, $e2->id, 'Mismo error con otro número: se agrupa.');
        $this->assertSame(2, $e2->ocurrencias);
        $this->assertMatchesRegularExpression('/^REF-[A-Z0-9]{6}$/', $e2->referencia);
        $this->assertSame($antes + 1, $this->avisos(), 'La repetición dentro de la hora no vuelve a avisar.');

        // Pasada la hora, si se repite vuelve a avisar; si estaba resuelto, se reabre.
        $e2->forceFill(['alertado_at' => now()->subHours(2), 'resuelto_at' => now()])->save();
        $e3 = RegistroErrores::registrar($falla(17));
        $this->assertSame($antes + 2, $this->avisos());
        $this->assertNull($e3->resuelto_at);

        // Las validaciones no son fallas del sistema: no se registran.
        report(ValidationException::withMessages(['valor' => 'x']));
        $this->assertSame(1, ErrorSistema::count());
    }

    public function test_pagina_500_en_espanol_con_el_codigo_que_queda_registrado(): void
    {
        config(['app.debug' => false]);
        Route::get('/prueba-falla', fn () => throw new RuntimeException('Falla de prueba'))->middleware('web');

        $respuesta = $this->get('/prueba-falla')->assertStatus(500)->assertSee('Algo falló de nuestro lado');
        $error = ErrorSistema::query()->where('mensaje', 'Falla de prueba')->firstOrFail();
        $respuesta->assertSee($error->referencia);
        $this->assertSame('Falla de prueba', $error->mensaje);
        $this->assertStringEndsWith('/prueba-falla', $error->url);
    }

    public function test_paginas_403_y_404_en_espanol(): void
    {
        config(['app.debug' => false]);
        $this->actingAs($this->usuario('auxiliar@demo.test'));
        $this->get('/app/cootrasec-demo/gerencia')->assertForbidden()->assertSee('No tiene permiso');
        $this->get('/app/cootrasec-demo/contratos/999999')->assertNotFound()->assertSee('No encontramos lo que busca');
        $this->assertSame(0, ErrorSistema::count());
    }

    public function test_latidos_y_vigilante(): void
    {
        $antes = $this->avisos();
        Latidos::marcar(Latidos::WORKER, true);
        $this->assertTrue(Latidos::vivo(Latidos::WORKER));

        $this->artisan('ruta:vigilar')->assertSuccessful();
        $this->assertSame($antes, $this->avisos(), 'Con el worker vivo y sin fallidos no avisa.');

        $this->travel(11)->minutes();
        $this->assertFalse(Latidos::vivo(Latidos::WORKER));
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'baja',
            'payload' => json_encode(['displayName' => EnviarWebhook::class]), 'exception' => "RuntimeException: caído\n#0", 'failed_at' => now()]);

        $this->artisan('ruta:vigilar')->assertSuccessful();
        $this->assertSame($antes + 2, $this->avisos(), 'Worker caído y un trabajo fallido.');

        $this->artisan('ruta:vigilar')->assertSuccessful();
        $this->assertSame($antes + 2, $this->avisos(), 'No repite el aviso dentro de la hora.');
    }

    public function test_pantalla_estado_del_sistema(): void
    {
        // Un trabajo real que falló: se encola, se toma su contenido y se pasa a fallidos.
        config(['queue.default' => 'database']);
        dispatch(new EnviarSms(999999, '3000000000', 'Hola'));
        $trabajo = DB::table('jobs')->where('queue', 'alta')->first();
        $uuid = json_decode($trabajo->payload, true)['uuid'];
        DB::table('jobs')->delete();
        DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'database', 'queue' => 'alta', 'payload' => $trabajo->payload,
            'exception' => "RuntimeException: Onurix no responde\n#0", 'failed_at' => now()]);
        RegistroErrores::registrar(new RuntimeException('Error visible en la pantalla'));

        $this->actingAs($this->superadmin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get('/admin/estado-sistema')->assertOk()->assertSee('Worker de la cola')->assertSee('EnviarSms')->assertSee('Onurix no responde');

        Livewire::test(EstadoSistema::class)
            ->assertSee('Error visible en la pantalla')
            ->call('reintentar', $uuid);
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('jobs')->where('queue', 'alta')->count());

        $error = ErrorSistema::firstOrFail();
        Livewire::test(EstadoSistema::class)->callTableAction('resolver', $error);
        $this->assertNotNull($error->refresh()->resuelto_at);

        // Un usuario de empresa no entra.
        $this->actingAs($this->usuario('admin@demo.test'));
        $this->get('/admin/estado-sistema')->assertForbidden();
    }

    public function test_sin_creditos_en_onurix_no_es_error_del_sistema_ni_se_reintenta(): void
    {
        $this->demo->forceFill(['sms_habilitado' => true, 'onurix_cliente' => '9999', 'onurix_clave' => 'clave-de-prueba'])->save();
        $intentos = 0;
        \Illuminate\Support\Facades\Http::fake(function () use (&$intentos) {
            $intentos++;

            return \Illuminate\Support\Facades\Http::response(['status' => 'error', 'msg' => 'Necesita mas créditos para hacer el envió'], 400);
        });

        config(['queue.default' => 'database']);
        $envio = \App\Services\Mensajeria\Mensajero::sms($this->demo, 'recordatorio_firma', '3001234567', 'Hola');
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'alta,default,baja', '--memory' => 2048])->assertSuccessful();

        $envio->refresh();
        $this->assertSame('fallido', $envio->estado);
        $this->assertStringContainsString('créditos', $envio->error);
        $this->assertSame(1, $intentos);
        $this->assertSame(0, DB::table('jobs')->where('queue', 'alta')->count(), 'No queda pendiente de reintento.');
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(0, ErrorSistema::count(), 'No es un error del sistema.');

        // La empresa recibe un aviso (una vez al día). La campana también va por la cola.
        $this->artisan('queue:work', ['--stop-when-empty' => true, '--queue' => 'alta,default,baja', '--memory' => 2048])->assertSuccessful();
        $admin = $this->usuario('admin@demo.test');
        $this->assertSame(1, $admin->notifications()->where('data', 'like', '%Los SMS no se est%')->count());
        \App\Services\Mensajeria\Mensajero::sms($this->demo, 'recordatorio_firma', '3001234567', 'Otra', ahora: true);
        $this->artisan('queue:work', ['--stop-when-empty' => true, '--queue' => 'alta,default,baja', '--memory' => 2048])->assertSuccessful();
        $this->assertSame(1, $admin->notifications()->where('data', 'like', '%Los SMS no se est%')->count());
    }

    public function test_falla_pasajera_se_reintenta_sin_registrar_error(): void
    {
        $this->demo->forceFill(['sms_habilitado' => true, 'onurix_cliente' => '9999', 'onurix_clave' => 'clave-de-prueba'])->save();
        \Illuminate\Support\Facades\Http::fake(fn () => \Illuminate\Support\Facades\Http::response('Bad gateway', 502));

        config(['queue.default' => 'database']);
        \App\Services\Mensajeria\Mensajero::sms($this->demo, 'recordatorio_firma', '3001234567', 'Hola');
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'alta,default,baja', '--memory' => 2048])->assertSuccessful();

        $trabajo = DB::table('jobs')->where('queue', 'alta')->first();
        $this->assertNotNull($trabajo, 'Vuelve a la cola para reintentar.');
        $this->assertGreaterThan(now()->getTimestamp() + 20, $trabajo->available_at, 'Con espera de 30 s.');
        $this->assertSame(0, ErrorSistema::count());
    }
}
