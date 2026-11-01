<?php

namespace Tests\Unit;

use App\Services\Mensajeria\OnurixSms;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnurixSmsTest extends TestCase
{
    public function test_ip_no_permitida_indica_la_ip_del_servidor(): void
    {
        Cache::forget('ip_salida_servidor');
        Http::fake([
            'api.ipify.org*' => Http::response('203.0.113.25'),
            '*send-sms*' => Http::response(['status' => 'error', 'msg' => "Ip's no permitidas"], 403),
        ]);

        $resultado = (new OnurixSms('1', 'clave'))->enviar('3001234567', 'hola');

        $this->assertFalse($resultado->exito);
        $this->assertStringContainsString('203.0.113.25', $resultado->error);
        $this->assertStringContainsString('Autorice en su cuenta de Onurix', $resultado->error);
    }
}
