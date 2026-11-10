<?php

namespace Tests\Feature\Sistema;

use App\Jobs\EnviarAvisoPlataforma;
use App\Support\Sistema\Latidos;
use App\Support\Sistema\RegistroErrores;
use App\Support\Sistema\Salud;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Monitoreo de salud: chequeos de la app y sus servicios, endpoint para monitores externos
 * y aviso por webhook cuando algo falla o aparece un error inesperado.
 */
class SaludTest extends ConfiguracionTestCase
{
    private function procesosVivos(): void
    {
        Latidos::marcar(Latidos::WORKER, true);
        Latidos::marcar(Latidos::PROGRAMADAS, true);
    }

    public function test_el_endpoint_responde_ok_cuando_todo_esta_sano(): void
    {
        $this->procesosVivos();

        $this->getJson('/salud')
            ->assertOk()
            ->assertJsonPath('estado', 'ok')
            ->assertJsonStructure(['estado', 'fecha', 'chequeos' => [['clave', 'nombre', 'ok', 'detalle']]]);
    }

    public function test_el_endpoint_responde_503_si_un_servicio_esta_caido(): void
    {
        // Sin latidos, el worker y las tareas programadas figuran caídos.
        $this->getJson('/salud')
            ->assertStatus(503)
            ->assertJsonPath('estado', 'falla');
    }

    public function test_el_token_protege_el_endpoint(): void
    {
        config(['ruta.salud_token' => 'secreto-de-prueba']);
        $this->procesosVivos();

        $this->getJson('/salud')->assertUnauthorized();
        $this->getJson('/salud?token=incorrecto')->assertUnauthorized();
        $this->getJson('/salud?token=secreto-de-prueba')->assertOk();
    }

    public function test_los_chequeos_detectan_el_worker_caido(): void
    {
        $fallando = collect(Salud::fallando())->pluck('clave')->all();

        $this->assertContains('worker', $fallando);
        $this->assertContains('programadas', $fallando);
    }

    public function test_un_error_inesperado_se_avisa_por_webhook(): void
    {
        config(['ruta.alertas_webhook_url' => 'https://ejemplo.test/hook']);
        Queue::fake();

        RegistroErrores::registrar(new RuntimeException('Falla con webhook'));

        Queue::assertPushed(EnviarAvisoPlataforma::class);
    }

    public function test_sin_webhook_configurado_no_se_encola_ningun_aviso(): void
    {
        config(['ruta.alertas_webhook_url' => null]);
        Queue::fake();

        RegistroErrores::registrar(new RuntimeException('Falla sin webhook'));

        Queue::assertNotPushed(EnviarAvisoPlataforma::class);
    }

    public function test_el_chequeo_de_correo_falla_si_el_proveedor_rechaza_la_conexion(): void
    {
        // Puerto cerrado: el saludo SMTP falla de inmediato, como con una clave inválida.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        Cache::forget('salud.smtp');
        Mail::purge('smtp');

        $correo = collect(Salud::chequeos())->firstWhere('clave', 'proveedor_correo');

        $this->assertFalse($correo['ok']);
        $this->assertStringContainsString('rechaza', $correo['detalle']);
    }

    public function test_el_chequeo_de_correo_no_comprueba_credenciales_sin_smtp(): void
    {
        config(['mail.default' => 'array']);
        Cache::forget('salud.smtp');

        $correo = collect(Salud::chequeos())->firstWhere('clave', 'proveedor_correo');

        $this->assertTrue($correo['ok']);
        $this->assertStringNotContainsString('Credenciales', $correo['detalle']);
    }

    public function test_el_detalle_dice_que_las_credenciales_se_comprobaron(): void
    {
        // Sin comprobación no se puede distinguir «autenticó» de «nadie ha enviado nada».
        config(['mail.default' => 'smtp']);
        Cache::put('salud.smtp', ['ok' => true, 'detalle' => ''], now()->addMinute());

        $correo = collect(Salud::chequeos())->firstWhere('clave', 'proveedor_correo');

        $this->assertTrue($correo['ok']);
        $this->assertStringContainsString('Credenciales válidas', $correo['detalle']);
    }
}
