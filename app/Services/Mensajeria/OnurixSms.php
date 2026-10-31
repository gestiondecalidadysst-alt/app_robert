<?php

namespace App\Services\Mensajeria;

use Illuminate\Http\Client\ConnectionException;
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

    private static function error(int $estado, array $json): string
    {
        $mensaje = $json['message'] ?? $json['msg'] ?? null;

        return match (true) {
            $estado === 401 => 'Onurix rechazó las credenciales (revise el Client ID y la clave).',
            $mensaje !== null => "Onurix: {$mensaje}",
            default => "Onurix respondió con error {$estado}.",
        };
    }
}
