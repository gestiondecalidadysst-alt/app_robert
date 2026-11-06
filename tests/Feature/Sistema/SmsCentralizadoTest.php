<?php

namespace Tests\Feature\Sistema;

use App\Filament\Admin\Pages\ConsumoSms;
use App\Filament\Admin\Pages\ProveedorSmsPagina;
use App\Models\EnvioMensaje;
use App\Models\ProveedorSms;
use App\Services\Mensajeria\Mensajero;
use App\Support\Sms;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * SMS como servicio de la plataforma: una sola cuenta Onurix, que el superadministrador activa
 * por empresa y cobra por segmento. Cada envío registra segmentos, precio y costo para facturar.
 */
class SmsCentralizadoTest extends ConfiguracionTestCase
{
    private function cuentaGlobal(bool $activo = true, int $costo = 30, int $precio = 80): ProveedorSms
    {
        return ProveedorSms::query()->create([
            'proveedor' => 'onurix', 'activo' => $activo,
            'onurix_cliente' => '9999', 'onurix_clave' => 'clave',
            'costo_segmento' => $costo, 'precio_segmento' => $precio,
        ]);
    }

    private function fakeOnurixOk(): void
    {
        Http::fake(['*' => Http::response(['id' => 'REF123'], 200)]);
    }

    public function test_sin_cuenta_global_activa_no_se_puede_enviar(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->demo->forceFill(['sms_habilitado' => true])->save();

        // No hay ProveedorSms configurado.
        $this->assertStringContainsString('no está disponible', Mensajero::motivoSinSms($this->demo));
    }

    public function test_con_cuenta_activa_pero_empresa_sin_servicio_no_se_envia(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->cuentaGlobal();
        $this->demo->forceFill(['sms_habilitado' => false])->save();

        $this->assertStringContainsString('no está activo para esta empresa', Mensajero::motivoSinSms($this->demo));
    }

    public function test_el_envio_registra_segmentos_precio_y_costo(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->cuentaGlobal(costo: 30, precio: 80);
        $this->demo->forceFill(['sms_habilitado' => true])->save();
        $this->fakeOnurixOk();

        $envio = Mensajero::sms($this->demo, 'prueba', '3001112233', 'Mensaje corto de prueba', ahora: true);

        $this->assertSame('enviado', $envio->estado);
        $this->assertSame(1, $envio->segmentos);
        $this->assertSame(80, $envio->precio);   // 1 segmento × 80
        $this->assertSame(30, $envio->costo);    // 1 segmento × 30
    }

    public function test_el_precio_propio_de_la_empresa_manda_sobre_el_general(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->cuentaGlobal(precio: 80);
        $this->demo->forceFill(['sms_habilitado' => true, 'sms_precio' => 120])->save();
        $this->fakeOnurixOk();

        $envio = Mensajero::sms($this->demo, 'prueba', '3001112233', 'Corto', ahora: true);

        $this->assertSame(120, $envio->precio);
    }

    public function test_un_mensaje_largo_cuenta_varios_segmentos(): void
    {
        $this->assertSame(1, Sms::segmentos(str_repeat('a', 160)));
        $this->assertSame(2, Sms::segmentos(str_repeat('a', 161)));
        $this->assertSame(2, Sms::segmentos(str_repeat('a', 306)));
        $this->assertSame(3, Sms::segmentos(str_repeat('a', 307)));
    }

    public function test_un_sms_fallido_no_tiene_precio_ni_costo(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->cuentaGlobal();
        $this->demo->forceFill(['sms_habilitado' => true])->save();
        Http::fake(['*' => Http::response(['error' => 'Sin creditos'], 402)]);

        $envio = Mensajero::sms($this->demo, 'prueba', '3001112233', 'Corto', ahora: true);

        $this->assertSame('fallido', $envio->estado);
        $this->assertNull($envio->precio);
        $this->assertNull($envio->costo);
    }

    public function test_el_limite_mensual_lo_fija_el_superadmin(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->cuentaGlobal();
        $this->demo->forceFill(['sms_habilitado' => true, 'sms_limite_mensual' => 1])->save();
        $this->fakeOnurixOk();

        Mensajero::sms($this->demo, 'prueba', '3001112233', 'Uno', ahora: true);
        $segundo = Mensajero::sms($this->demo, 'prueba', '3001112233', 'Dos', ahora: true);

        $this->assertSame('omitido', $segundo->estado);
        $this->assertStringContainsString('límite', $segundo->error);
    }

    public function test_el_consumo_se_cuenta_por_empresa_y_solo_lo_enviado(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->cuentaGlobal(costo: 30, precio: 80);
        $this->demo->forceFill(['sms_habilitado' => true])->save();

        // Dos enviados y uno fallido (secuencia: el fake apilado no reemplaza).
        Http::fake(['*' => Http::sequence()
            ->push(['id' => 'R1'], 200)->push(['id' => 'R2'], 200)->push(['status' => 'error'], 402)]);
        Mensajero::sms($this->demo, 'prueba', '3001112233', 'Uno', ahora: true);
        Mensajero::sms($this->demo, 'prueba', '3001112233', 'Dos', ahora: true);
        Mensajero::sms($this->demo, 'prueba', '3001112233', 'Falla', ahora: true);

        $enviados = EnvioMensaje::query()->deEmpresa($this->demo)->where('canal', 'sms')->where('estado', 'enviado')->get();
        $this->assertCount(2, $enviados);
        $this->assertSame(160, $enviados->sum('precio'));  // 2 × 80
        $this->assertSame(60, $enviados->sum('costo'));     // 2 × 30
    }

    public function test_la_pagina_de_cuenta_guarda_las_credenciales(): void
    {
        $this->entrarComo('superadmin@ruta.test');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ProveedorSmsPagina::class)
            ->fillForm(['activo' => true, 'onurix_cliente' => 'ABC', 'onurix_clave' => 'secreta', 'costo_segmento' => 25, 'precio_segmento' => 70])
            ->call('guardar');

        $p = ProveedorSms::actual();
        $this->assertTrue($p->configurado());
        $this->assertSame('secreta', $p->onurix_clave);
        $this->assertSame(70, $p->precio_segmento);
    }

    public function test_la_pagina_de_consumo_resume_por_empresa(): void
    {
        $this->entrarComo('admin@demo.test');
        $this->cuentaGlobal(costo: 30, precio: 80);
        $this->demo->forceFill(['sms_habilitado' => true])->save();
        $this->fakeOnurixOk();
        Mensajero::sms($this->demo, 'prueba', '3001112233', 'Uno', ahora: true);

        $this->flushSession();
        $this->entrarComo('superadmin@ruta.test');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $pagina = new ConsumoSms;
        $pagina->mes = now()->format('Y-m');
        $resumen = $pagina->resumen();

        $this->assertCount(1, $resumen);
        $this->assertSame(1, $resumen->first()['mensajes']);
        $this->assertSame(80, $resumen->first()['cobrar']);
    }
}
