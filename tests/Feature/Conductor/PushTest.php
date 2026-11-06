<?php

namespace Tests\Feature\Conductor;

use App\Models\SuscripcionPush;
use App\Models\User;
use App\Support\Push\WebPush;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Configuracion\ConfiguracionTestCase;

/**
 * Notificaciones Web Push del portal del conductor (PWA fase 2): cifrado, VAPID, suscripción del
 * navegador y envío. La entrega real solo se comprueba contra un servicio de push en producción;
 * aquí se verifica el cifrado (ida y vuelta) y que la petición sale bien formada.
 */
class PushTest extends ConfiguracionTestCase
{
    /** Configura un par de llaves VAPID de prueba. */
    private function configurarVapid(): void
    {
        $pk = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($pk, $pem);
        $d = openssl_pkey_get_details($pk);
        $publica = WebPush::b64url("\x04".$d['ec']['x'].$d['ec']['y']);

        config([
            'webpush.public_key' => $publica,
            'webpush.private_pem' => base64_encode($pem),
            'webpush.subject' => 'mailto:pruebas@demo.test',
        ]);
    }

    /** Suscripción de navegador de prueba (llaves válidas de un par EC real). */
    private function suscribir(User $usuario, string $endpoint = 'https://push.example.test/abc'): SuscripcionPush
    {
        $pk = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $d = openssl_pkey_get_details($pk);

        return SuscripcionPush::registrar($usuario, [
            'endpoint' => $endpoint,
            'p256dh' => WebPush::b64url("\x04".$d['ec']['x'].$d['ec']['y']),
            'auth' => WebPush::b64url(random_bytes(16)),
        ]);
    }

    public function test_el_cifrado_web_push_va_y_vuelve(): void
    {
        $ua = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $d = openssl_pkey_get_details($ua);
        $uaPublic = "\x04".$d['ec']['x'].$d['ec']['y'];
        $auth = random_bytes(16);
        $mensaje = json_encode(['title' => 'Prueba', 'body' => 'Hola ñ áé', 'url' => '/conductor']);

        $body = (new WebPush)->cifrarContenido($uaPublic, $auth, $mensaje);

        // Descifrar como lo haría el navegador.
        $salt = substr($body, 0, 16);
        $idlen = ord($body[20]);
        $asPublic = substr($body, 21, $idlen);
        $resto = substr($body, 21 + $idlen);
        $tag = substr($resto, -16);
        $cipher = substr($resto, 0, -16);

        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$asPublic;
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n").'-----END PUBLIC KEY-----';
        $ecdh = openssl_pkey_derive(openssl_pkey_get_public($pem), $ua, 32);

        $ikm = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0".$uaPublic.$asPublic, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        $plano = rtrim(openssl_decrypt($cipher, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag), "\x02");

        $this->assertSame($mensaje, $plano);
        $this->assertSame(65, $idlen);
    }

    public function test_el_jwt_vapid_es_es256(): void
    {
        $this->configurarVapid();
        $jwt = (new WebPush)->jwt('https://fcm.googleapis.com');

        $partes = explode('.', $jwt);
        $this->assertCount(3, $partes);
        $cabecera = json_decode(base64_decode(strtr($partes[0], '-_', '+/')), true);
        $this->assertSame('ES256', $cabecera['alg']);
        $cuerpo = json_decode(base64_decode(strtr($partes[1], '-_', '+/')), true);
        $this->assertSame('https://fcm.googleapis.com', $cuerpo['aud']);
        $this->assertSame(64, strlen(WebPush::b64urlDecode($partes[2]))); // r||s crudos
    }

    public function test_el_navegador_registra_su_suscripcion(): void
    {
        $this->post('/conductor/ingresar', ['documento' => '20.000.001', 'clave' => 'Ruta2026*'])
            ->assertRedirect(route('conductor.inicio'));

        $datos = ['endpoint' => 'https://push.example.test/uno', 'keys' => ['p256dh' => 'AAAA', 'auth' => 'BBBB']];
        $this->postJson(route('conductor.push.suscribir'), $datos)->assertNoContent();
        $this->postJson(route('conductor.push.suscribir'), $datos)->assertNoContent();

        $this->assertSame(1, SuscripcionPush::query()->where('endpoint', 'https://push.example.test/uno')->count());
    }

    public function test_el_envio_usa_vapid_y_cifrado_aes128gcm(): void
    {
        $this->configurarVapid();
        Http::fake(['*' => Http::response('', 201)]);

        $usuario = $this->usuario('admin@demo.test');
        $this->suscribir($usuario, 'https://push.example.test/destino');

        $enviadas = WebPush::aUsuario($usuario, 'Hola', 'Cuerpo de prueba', '/conductor', 'prueba');

        $this->assertSame(1, $enviadas);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://push.example.test/destino'
                && str_starts_with($request->header('Authorization')[0], 'vapid t=')
                && $request->header('Content-Encoding')[0] === 'aes128gcm'
                && strlen((string) $request->body()) > 80;
        });
    }

    public function test_una_suscripcion_cancelada_se_da_de_baja(): void
    {
        $this->configurarVapid();
        Http::fake(['*' => Http::response('', 410)]);

        $usuario = $this->usuario('admin@demo.test');
        $this->suscribir($usuario, 'https://push.example.test/vieja');

        $enviadas = WebPush::aUsuario($usuario, 'Hola', 'Cuerpo', '/conductor');

        $this->assertSame(0, $enviadas);
        $this->assertSame(0, $usuario->suscripcionesPush()->count());
    }

    public function test_sin_llaves_vapid_no_se_envia_nada(): void
    {
        config(['webpush.public_key' => null, 'webpush.private_pem' => null]);
        Http::fake();

        $usuario = $this->usuario('admin@demo.test');
        $this->suscribir($usuario);

        $this->assertFalse(WebPush::configurado());
        $this->assertSame(0, WebPush::aUsuario($usuario, 'Hola', 'Cuerpo'));
        Http::assertNothingSent();
    }
}
