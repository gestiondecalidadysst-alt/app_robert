<?php

namespace App\Services\Transito;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Convierte las respuestas del proveedor en datos del sistema. Tolera nombres de campo distintos
 * (multas/comparendos/resoluciones, comparendoId/numero…) para no romperse si el proveedor cambia.
 */
final class Interpretar
{
    /**
     * @return array{comparendos: list<array>, deuda: float}
     */
    public static function comparendos(array $json): array
    {
        $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
        $items = [];
        foreach (['multas', 'comparendos', 'resoluciones'] as $clave) {
            if (is_array($data[$clave] ?? null) && array_is_list($data[$clave])) {
                $items = [...$items, ...$data[$clave]];
            }
        }
        // Acuerdos de pago (estado de cuenta): número = resolución; valor = lo pendiente.
        foreach (['acuerdos', 'acuerdosPago'] as $clave) {
            if (is_array($data[$clave] ?? null) && array_is_list($data[$clave])) {
                foreach ($data[$clave] as $a) {
                    if (is_array($a) && filled($a['resolucion'] ?? null)) {
                        $items[] = ['numero' => 'AC-' . $a['resolucion'], 'fecha' => $a['fechaResolucion'] ?? null, 'valor' => $a['pendiente'] ?? $a['valorAcuerdo'] ?? 0,
                            'estado' => 'acuerdo de pago', 'tipo' => 'acuerdo', 'organismo' => $a['secretaria'] ?? null, 'infraccion' => 'Acuerdo de pago ' . ($a['estado'] ?? '')];
                    }
                }
            }
        }

        $comparendos = [];
        foreach ($items as $i) {
            if (! is_array($i)) {
                continue;
            }
            $numero = (string) (self::primero($i, ['comparendoId', 'numeroComparendo', 'nroComparendo', 'numero', 'numeroResolucion', 'id']) ?? '');
            if ($numero === '') {
                continue;
            }
            $infraccion = $i['infraccion'] ?? null;
            if (is_array($infraccion)) {
                $infraccion = $infraccion['descripcion'] ?? $infraccion[0]['descripcion'] ?? null;
            }
            $comparendos[$numero] = [
                'numero' => Str::limit($numero, 60, ''),
                'fecha' => self::fecha(self::primero($i, ['fecha', 'fechaComparendo', 'fechaInfraccion'])),
                'codigo' => Str::limit((string) (self::primero($i, ['codigo', 'codigoInfraccion']) ?? ''), 20, '') ?: null,
                'infraccion' => $infraccion ?? self::primero($i, ['descripcion', 'descripcionInfraccion']),
                'valor' => self::numero(self::primero($i, ['valor', 'valorPagar', 'total', 'saldo'])),
                'estado' => self::estado((string) (self::primero($i, ['estado', 'estadoComparendo']) ?? '')),
                'tipo' => self::primero($i, ['tipo']),
                'organismo' => self::primero($i, ['organismo', 'secretaria', 'organismoTransito']),
                'placa' => Str::upper((string) (self::primero($i, ['placa']) ?? '')) ?: null,
            ];
        }

        $deuda = self::numero(self::primero($data, ['totalDeuda', 'total_deuda', 'totalMultasPagar']));
        if ($deuda <= 0) {
            $deuda = (float) collect($comparendos)->whereIn('estado', ['pendiente', 'acuerdo'])->sum('valor');
        }

        return ['comparendos' => array_values($comparendos), 'deuda' => $deuda];
    }

    /**
     * @return array{estado: ?string, suspendida: bool, categorias: list<array>, tiene_multas: ?bool}
     */
    public static function licencia(array $json): array
    {
        $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
        $licencias = is_array($data['licenses'] ?? $data['licencias'] ?? null) ? ($data['licenses'] ?? $data['licencias']) : [];

        $categorias = [];
        $suspendida = Str::contains(Str::upper((string) ($data['driverStatus'] ?? '')), 'SUSPEN');
        foreach ($licencias as $l) {
            if (! is_array($l)) {
                continue;
            }
            $estado = Str::upper((string) ($l['status'] ?? $l['estado'] ?? ''));
            $finSuspension = self::fecha($l['endDateSuspension'] ?? null);
            if (Str::contains($estado, 'SUSPEN') || ($finSuspension && Carbon::parse($finSuspension)->isFuture())) {
                $suspendida = true;
            }
            $categorias[] = [
                'categoria' => $l['category'] ?? $l['categoria'] ?? null,
                'estado' => $estado ?: null,
                'vence' => self::fecha($l['dueDate'] ?? $l['fechaVencimiento'] ?? null),
                'restricciones' => $l['restrictions'] ?? $l['restricciones'] ?? null,
                'fin_suspension' => $finSuspension,
            ];
        }

        $multas = $data['infractions']['tieneMultas'] ?? null;

        return [
            'estado' => $data['driverStatus'] ?? null,
            'suspendida' => $suspendida,
            'categorias' => $categorias,
            'tiene_multas' => $multas === null ? null : Str::upper((string) $multas) === 'SI',
        ];
    }

    /**
     * @return array{soat_vence: ?string, soat_estado: ?string, rtm_vence: ?string, rtm_vigente: ?bool, estado_vehiculo: ?string}
     */
    public static function runt(array $json): array
    {
        $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
        $soat = Arr::first((array) ($data['soat'] ?? []));
        $rtm = collect((array) ($data['tecnoMecanica'] ?? []))->first(fn ($r) => Str::upper((string) ($r['vigente'] ?? '')) === 'SI')
            ?? Arr::first((array) ($data['tecnoMecanica'] ?? []));

        return [
            'soat_vence' => is_array($soat) ? self::fecha($soat['fechaVencimiento'] ?? null) : null,
            'soat_estado' => is_array($soat) ? ($soat['estado'] ?? null) : null,
            'rtm_vence' => is_array($rtm) ? self::fecha($rtm['fechaVencimiento'] ?? null) : null,
            'rtm_vigente' => is_array($rtm) ? Str::upper((string) ($rtm['vigente'] ?? '')) === 'SI' : null,
            'estado_vehiculo' => $data['informacionGeneral']['estado'] ?? $data['informacionGeneral']['estadoAutomotor'] ?? null,
        ];
    }

    private static function primero(array $fila, array $claves): mixed
    {
        foreach ($claves as $c) {
            if (array_key_exists($c, $fila) && $fila[$c] !== null && $fila[$c] !== '') {
                return $fila[$c];
            }
        }

        return null;
    }

    private static function estado(string $texto): string
    {
        $t = Str::lower(Str::ascii($texto));

        return match (true) {
            str_contains($t, 'acuerdo') => 'acuerdo',
            $t === '' || str_contains($t, 'pend') => 'pendiente', // «Pendiente de pago» no está pagado
            str_contains($t, 'pagad') || str_contains($t, 'pago') => 'pagada',
            str_contains($t, 'sin pagar') || str_contains($t, 'vigente') || str_contains($t, 'cobro') || str_contains($t, 'embargo') => 'pendiente',
            default => 'otro',
        };
    }

    private static function numero(mixed $v): float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        $limpio = preg_replace('/[^\d,.-]/', '', (string) $v);
        $limpio = str_contains($limpio, ',') ? str_replace(['.', ','], ['', '.'], $limpio) : str_replace('.', '', $limpio);

        return is_numeric($limpio) ? (float) $limpio : 0.0;
    }

    /** "15/08/2026", "2026-08-15", "2026-08-27T00:00:00.000-05:00" → "2026-08-15" */
    public static function fecha(mixed $v): ?string
    {
        if (blank($v) || ! is_string($v)) {
            return null;
        }
        try {
            return preg_match('#^\d{1,2}/\d{1,2}/\d{4}#', $v)
                ? Carbon::createFromFormat('d/m/Y', substr($v, 0, 10))->toDateString()
                : Carbon::parse($v)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
