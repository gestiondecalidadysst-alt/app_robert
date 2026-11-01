<?php

namespace App\Services\Mensajeria;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Envío de SMS con Onurix (https://www.onurix.com). Cada empresa usa sus propias credenciales
 * (Configuración → Integraciones). La URL se puede cambiar con ONURIX_URL si Onurix la cambia.
 */
final class OnurixSms
{
    public function __construct(
        private readonly string $cliente,
        private readonly string $clave,
    ) {}

    public function enviar(string $celular, string $texto): ResultadoSms
    {
        try {
            $respuesta = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->post(self::url('/send-sms'), [
                    'client' => $this->cliente,
                    'key' => $this->clave,
                    'phone' => $celular,
                    'sms' => $texto,
                    'country_code' => 'CO',
                ]);
        } catch (ConnectionException $e) {
            return new ResultadoSms(false, error: 'No hubo conexión con Onurix: ' . $e->getMessage());
        }

        $json = $respuesta->json() ?? [];

        if ($respuesta->failed() || ($json['status'] ?? null) === 'error') {
            return new ResultadoSms(false, error: self::error($respuesta->status(), $json));
        }

        $referencia = $json['transactionId'] ?? $json['id'] ?? null;

        return new ResultadoSms(true, referencia: $referencia ? (string) $referencia : null);
    }

    /** Saldo de la cuenta (null si no se pudo consultar). */
    public function saldo(): ?float
    {
        try {
            $respuesta = Http::acceptJson()->timeout(15)->get(self::url('/balance'), [
                'client' => $this->cliente,
                'key' => $this->clave,
            ]);
        } catch (ConnectionException) {
            return null;
        }

        $saldo = $respuesta->json('balance');

        return $respuesta->successful() && is_numeric($saldo) ? (float) $saldo : null;
    }

    public static function url(string $ruta): string
    {
        return rtrim((string) config('ruta.onurix.url'), '/') . $ruta;
    }

    /**
     * IP pública con la que este servidor sale a internet. Onurix solo acepta peticiones de las IP
     * registradas en la cuenta, así que esta es la que hay que autorizar allá. Null si no se pudo saber.
     */
    public static function ipSalida(): ?string
    {
        $ip = Cache::get('ip_salida_servidor');
        if ($ip) {
            return $ip;
        }

        foreach (['https://api.ipify.org', 'https://ifconfig.me/ip', 'https://icanhazip.com'] as $servicio) {
            try {
                $respuesta = trim((string) Http::timeout(5)->get($servicio)->body());
            } catch (\Throwable) {
                continue;
            }
            if (filter_var($respuesta, FILTER_VALIDATE_IP)) {
                Cache::put('ip_salida_servidor', $respuesta, now()->addHour());

                return $respuesta;
            }
        }

        return null;
    }

    private static function error(int $estado, array $json): string
    {
        $mensaje = $json['message'] ?? $json['msg'] ?? null;

        if ($mensaje !== null && str_contains(mb_strtolower((string) $mensaje), 'ip')) {
            $ip = self::ipSalida();

            return "Onurix: {$mensaje}. Autorice en su cuenta de Onurix la IP de este servidor"
                . ($ip ? ": {$ip}" : ' (no se pudo averiguar; use «Ver IP del servidor»)') . '.';
        }

        return match (true) {
            $estado === 401 => 'Onurix rechazó las credenciales (revise el Client ID y la clave).',
            $mensaje !== null => "Onurix: {$mensaje}",
            default => "Onurix respondió con error {$estado}.",
        };
    }
}
