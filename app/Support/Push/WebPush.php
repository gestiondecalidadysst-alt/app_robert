<?php

namespace App\Support\Push;

use App\Models\SuscripcionPush;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;

/**
 * Envío de notificaciones Web Push (RFC 8030/8188/8291) sin librerías externas: usa openssl y
 * hash_hkdf, que ya están en el servidor. Cifra el mensaje con aes128gcm para la suscripción del
 * navegador y autentica ante el servicio de push (FCM/Apple/Mozilla) con VAPID (JWT ES256).
 *
 * La entrega real solo se puede comprobar contra un servicio de push real (en producción); aquí el
 * cifrado se prueba cifrando y descifrando de vuelta.
 */
class WebPush
{
    /** ¿Hay llaves VAPID configuradas? Sin ellas no se envía nada (y la app no se rompe). */
    public static function configurado(): bool
    {
        return filled(config('webpush.public_key')) && filled(config('webpush.private_pem'));
    }

    /**
     * Envía a todas las suscripciones de un usuario. Devuelve cuántas recibieron el aviso.
     * Da de baja las suscripciones que el navegador ya canceló (404/410).
     */
    public static function aUsuario(User $usuario, string $titulo, string $cuerpo, string $url = '/conductor', ?string $tag = null): int
    {
        if (! self::configurado()) {
            return 0;
        }

        $enviadas = 0;
        foreach ($usuario->suscripcionesPush()->get() as $suscripcion) {
            if ((new self)->enviar($suscripcion, ['title' => $titulo, 'body' => $cuerpo, 'url' => $url, 'tag' => $tag]) === 'ok') {
                $enviadas++;
            }
        }

        return $enviadas;
    }

    /** @return 'ok'|'baja'|'error'|'omitido' */
    public function enviar(SuscripcionPush $suscripcion, array $payload): string
    {
        if (! self::configurado()) {
            return 'omitido';
        }

        $cuerpo = $this->cifrarContenido(
            self::b64urlDecode($suscripcion->p256dh),
            self::b64urlDecode($suscripcion->auth),
            json_encode($payload, JSON_UNESCAPED_UNICODE),
        );

        $origen = $this->origen($suscripcion->endpoint);

        $respuesta = Http::withHeaders([
            'Authorization' => 'vapid t='.$this->jwt($origen).', k='.config('webpush.public_key'),
            'Content-Encoding' => 'aes128gcm',
            'TTL' => '2419200',
            'Urgency' => 'normal',
        ])->withBody($cuerpo, 'application/octet-stream')->post($suscripcion->endpoint);

        if (in_array($respuesta->status(), [404, 410], true)) {
            $suscripcion->delete();

            return 'baja';
        }

        return $respuesta->successful() ? 'ok' : 'error';
    }

    /**
     * Cifra el mensaje para la suscripción (aes128gcm, RFC 8291). Devuelve el cuerpo completo listo
     * para enviar: cabecera (salt, tamaño de registro, clave pública efímera) + texto cifrado + tag.
     */
    public function cifrarContenido(string $uaPublic, string $authSecret, string $mensaje): string
    {
        // Llave efímera del servidor para este envío.
        $as = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $det = openssl_pkey_get_details($as);
        $asPublic = "\x04".$det['ec']['x'].$det['ec']['y'];

        // Secreto compartido (ECDH) con la clave pública del navegador.
        $ecdh = openssl_pkey_derive($this->claveDesdePunto($uaPublic), $as, 32);

        $salt = random_bytes(16);

        // RFC 8291: material base combinando el secreto de autenticación y el ECDH.
        $info = "WebPush: info\0".$uaPublic.$asPublic;
        $ikm = hash_hkdf('sha256', $ecdh, 32, $info, $authSecret);

        // RFC 8188: clave y nonce del contenido.
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // Registro único: mensaje + delimitador 0x02 (último registro).
        $tag = '';
        $cifrado = openssl_encrypt($mensaje."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        // Cabecera RFC 8188: salt(16) | tamaño de registro(4) | long. keyid(1)=65 | keyid = clave pública efímera.
        $cabecera = $salt.pack('N', 4096).chr(65).$asPublic;

        return $cabecera.$cifrado.$tag;
    }

    /** JWT ES256 firmado con la clave privada VAPID, para el servicio de push. */
    public function jwt(string $audiencia): string
    {
        $cabecera = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $cuerpo = self::b64url(json_encode([
            'aud' => $audiencia,
            'exp' => time() + 12 * 3600,
            'sub' => config('webpush.subject'),
        ]));
        $entrada = $cabecera.'.'.$cuerpo;

        $privada = openssl_pkey_get_private(base64_decode((string) config('webpush.private_pem')));
        openssl_sign($entrada, $der, $privada, OPENSSL_ALGO_SHA256);

        return $entrada.'.'.self::b64url($this->derARaw($der));
    }

    /** Clave pública EC (OpenSSL) a partir del punto sin comprimir de 65 bytes del navegador. */
    private function claveDesdePunto(string $punto): OpenSSLAsymmetricKey
    {
        // Prefijo DER SubjectPublicKeyInfo para prime256v1 + punto sin comprimir.
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$punto;
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n").'-----END PUBLIC KEY-----';

        return openssl_pkey_get_public($pem);
    }

    /** Firma ECDSA DER (SEQUENCE de dos enteros) a formato crudo r||s de 64 bytes. */
    private function derARaw(string $der): string
    {
        // Estructura para P-256 (longitud < 128): 30 len 02 lenR R 02 lenS S.
        $lenR = ord($der[3]);
        $r = substr($der, 4, $lenR);
        $pos = 4 + $lenR + 1;      // salta R y el marcador 02 del entero S
        $lenS = ord($der[$pos]);
        $s = substr($der, $pos + 1, $lenS);

        $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);

        return $r.$s;
    }

    private function origen(string $url): string
    {
        $p = parse_url($url);

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
    }

    public static function b64url(string $datos): string
    {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $datos): string
    {
        return base64_decode(strtr($datos, '-_', '+/').str_repeat('=', (4 - strlen($datos) % 4) % 4));
    }
}
