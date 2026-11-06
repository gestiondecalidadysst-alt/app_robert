<?php

namespace Tests\Feature\Conductor;

use App\Models\ActividadMantenimiento;
use App\Models\AsignacionVehiculo;
use App\Models\Conductor;
use App\Models\Contrato;
use App\Models\Fuec;
use App\Models\Municipio;
use App\Models\SuscripcionPush;
use App\Models\User;
use App\Support\Push\NotificarConductor;
use App\Support\Push\WebPush;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Avisos push al conductor sobre lo suyo: nuevo extracto asignado, mantenimiento de su vehículo y
 * preoperacional pendiente. Se verifica que el aviso sale al conductor correcto (sus dispositivos).
 */
class NotificarConductorTest extends ConfiguracionTestCase
{
    private function configurarVapid(): void
    {
        $pk = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($pk, $pem);
        $d = openssl_pkey_get_details($pk);
        config([
            'webpush.public_key' => WebPush::b64url("\x04".$d['ec']['x'].$d['ec']['y']),
            'webpush.private_pem' => base64_encode($pem),
        ]);
    }

    private function conductorConDispositivo(): Conductor
    {
        $conductor = Conductor::query()->withoutGlobalScopes()->deEmpresa($this->demo)->whereNotNull('user_id')->firstOrFail();
        $user = User::findOrFail($conductor->user_id);

        $pk = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $d = openssl_pkey_get_details($pk);
        SuscripcionPush::registrar($user, [
            'endpoint' => 'https://push.example.test/'.Str::random(6),
            'p256dh' => WebPush::b64url("\x04".$d['ec']['x'].$d['ec']['y']),
            'auth' => WebPush::b64url(random_bytes(16)),
        ]);

        return $conductor;
    }

    public function test_avisa_al_conductor_con_dispositivo(): void
    {
        $this->configurarVapid();
        Http::fake(['*' => Http::response('', 201)]);
        $conductor = $this->conductorConDispositivo();

        $enviadas = NotificarConductor::aConductor($conductor, 'Hola', 'Mensaje');

        $this->assertSame(1, $enviadas);
        Http::assertSent(fn ($r) => str_starts_with($r->header('Authorization')[0], 'vapid t=')
            && $r->header('Content-Encoding')[0] === 'aes128gcm');
    }

    public function test_conductor_sin_usuario_no_recibe(): void
    {
        $this->configurarVapid();
        Http::fake();
        $conductor = new Conductor(['nombres' => 'Sin', 'apellidos' => 'Usuario']);

        $this->assertSame(0, NotificarConductor::aConductor($conductor, 'Hola', 'Mensaje'));
        Http::assertNothingSent();
    }

    public function test_el_mantenimiento_avisa_al_conductor_del_vehiculo(): void
    {
        $this->configurarVapid();
        Http::fake(['*' => Http::response('', 201)]);
        $conductor = $this->conductorConDispositivo();

        $vehiculoId = (int) AsignacionVehiculo::query()->withoutGlobalScopes()
            ->where('conductor_id', $conductor->id)->whereNull('fecha_hasta')->value('vehiculo_id');

        $actividad = new ActividadMantenimiento([
            'vehiculo_id' => $vehiculoId,
            'rutina_nombre' => 'Cambio de aceite',
            'fecha_programada' => today(),
        ]);

        NotificarConductor::mantenimiento($actividad, vencida: true);

        Http::assertSentCount(1);
    }

    public function test_el_preoperacional_pendiente_avisa_por_servicio_de_hoy(): void
    {
        $this->configurarVapid();
        Http::fake(['*' => Http::response('', 201)]);
        $conductor = $this->conductorConDispositivo();

        $vehiculoId = (int) AsignacionVehiculo::query()->withoutGlobalScopes()
            ->where('conductor_id', $conductor->id)->whereNull('fecha_hasta')->value('vehiculo_id');
        [$origen, $destino] = Municipio::query()->limit(2)->pluck('id')->all();

        $fuec = new Fuec([
            'contrato_id' => Contrato::query()->withoutGlobalScopes()->deEmpresa($this->demo)->value('id'),
            'consecutivo' => 999, 'numero' => 'FUEC-TEST-999',
            'vehiculo_id' => $vehiculoId,
            'fecha_inicio' => today(), 'fecha_fin' => today(),
            'origen_municipio_id' => $origen, 'destino_municipio_id' => $destino,
            'codigo_verificacion' => Str::lower(Str::random(24)),
            'datos' => [],
        ]);
        $fuec->empresa_id = $this->demo->id;
        $fuec->save();
        $fuec->conductores()->attach($conductor->id, ['orden' => 1]);

        $enviados = NotificarConductor::preoperacionalPendiente($this->demo);

        $this->assertGreaterThanOrEqual(1, $enviados);
        Http::assertSent(fn ($r) => $r->header('Content-Encoding')[0] === 'aes128gcm');
    }
}
