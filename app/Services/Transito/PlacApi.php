<?php

namespace App\Services\Transito;

use App\Enums\TipoConsultaTransito;
use App\Models\ProveedorTransito;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente de PlacApi (https://placapi.com/docs). Autenticación con el header x-api-key; POST JSON.
 * - conductor_comparendos → /api/estado-cuenta-transito (documento)
 * - conductor_licencia    → /api/licencia (documento y primer apellido)
 * - vehiculo_multas       → /api/multas (placa y documento del propietario)
 * - vehiculo_runt         → /api/consulta (placa y documento del propietario)
 * Solo cobra cuando hay datos; la misma consulta en 24 h viene de su caché y no se cobra.
 */
final class PlacApi
{
    public const RUTAS = [
        'conductor_comparendos' => '/api/estado-cuenta-transito',
        'conductor_licencia' => '/api/licencia',
        'vehiculo_multas' => '/api/multas',
        'vehiculo_runt' => '/api/consulta',
    ];

    public function __construct(private readonly ProveedorTransito $proveedor) {}

    /** @param  array<string, string>  $parametros */
    public function consultar(TipoConsultaTransito $tipo, array $parametros): ResultadoConsulta
    {
        $inicio = microtime(true);

        try {
            $respuesta = Http::acceptJson()->asJson()->timeout(45)->connectTimeout(10)
                ->withHeaders(['x-api-key' => (string) $this->proveedor->api_key])
                ->post(rtrim((string) $this->proveedor->url, '/') . self::RUTAS[$tipo->value], array_filter($parametros, 'filled'));
        } catch (ConnectionException $e) {
            return new ResultadoConsulta('error', error: 'No hubo conexión con el proveedor: ' . $e->getMessage(), duracionMs: self::ms($inicio));
        } catch (Throwable $e) {
            return new ResultadoConsulta('error', error: $e->getMessage(), duracionMs: self::ms($inicio));
        }

        $json = $respuesta->json() ?? [];
        $ms = self::ms($inicio);

        if ($respuesta->status() === 404) {
            return new ResultadoConsulta('sin_datos', datos: $json, duracionMs: $ms);
        }
        if (! $respuesta->successful()) {
            return new ResultadoConsulta('error', datos: is_array($json) ? $json : [], error: self::mensaje($respuesta->status(), $json), duracionMs: $ms);
        }

        $cache = (bool) ($json['fromCache'] ?? false);

        return new ResultadoConsulta('ok', datos: is_array($json) ? $json : [], cobrada: ! $cache, desdeCache: $cache, duracionMs: $ms);
    }

    private static function mensaje(int $status, mixed $json): string
    {
        $detalle = is_array($json) ? ($json['message'] ?? $json['error'] ?? $json['msg'] ?? null) : null;
        $detalle = is_array($detalle) ? json_encode($detalle) : $detalle;

        return match ($status) {
            401 => 'El proveedor rechazó la API key.',
            402 => 'La cuenta del proveedor no tiene saldo (recargue créditos).',
            429 => 'Demasiadas consultas seguidas al proveedor; intente en un momento.',
            default => "El proveedor respondió {$status}" . ($detalle ? ": {$detalle}" : '.'),
        };
    }

    private static function ms(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
