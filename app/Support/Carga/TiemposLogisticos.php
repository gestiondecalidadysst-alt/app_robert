<?php

namespace App\Support\Carga;

use App\Models\Conductor;
use App\Models\RemesaCarga;
use App\Models\TiempoLogistico;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Registro de los tiempos logísticos de una remesa.
 *
 * Se marcan en orden (llegada → entrada → salida) y primero el cargue, luego el
 * descargue. Marcar fuera de orden es casi siempre un toque por error en el celular,
 * y un tiempo mal reportado es peor que uno sin reportar.
 */
final class TiemposLogisticos
{
    /** Qué se puede marcar ahora mismo en esta remesa: [punto, evento] o null si ya está todo. */
    public static function siguiente(RemesaCarga $remesa): ?array
    {
        $hechos = self::registrados($remesa);

        foreach (array_keys(TiempoLogistico::PUNTOS) as $punto) {
            foreach (TiempoLogistico::ORDEN as $evento) {
                if (! isset($hechos["{$punto}.{$evento}"])) {
                    return [$punto, $evento];
                }
            }
        }

        return null;
    }

    /** @return Collection<string, TiempoLogistico> indexada por «punto.evento» */
    public static function registrados(RemesaCarga $remesa): Collection
    {
        return $remesa->tiemposLogisticos()->get()->keyBy(fn (TiempoLogistico $t) => "{$t->punto}.{$t->evento}");
    }

    public static function completos(RemesaCarga $remesa): bool
    {
        return self::siguiente($remesa) === null;
    }

    /**
     * Marca un evento. Devuelve el registro creado.
     *
     * @param  array{latitud?: float|null, longitud?: float|null, observacion?: string|null, ocurrido_at?: \DateTimeInterface|null}  $datos
     */
    public static function marcar(
        RemesaCarga $remesa,
        string $punto,
        string $evento,
        ?Conductor $conductor = null,
        ?User $usuario = null,
        array $datos = [],
    ): TiempoLogistico {
        if (! isset(TiempoLogistico::PUNTOS[$punto]) || ! in_array($evento, TiempoLogistico::ORDEN, true)) {
            throw ValidationException::withMessages(['evento' => 'Ese momento del viaje no existe.']);
        }

        if ($remesa->anulado()) {
            throw ValidationException::withMessages(['evento' => 'La remesa está anulada.']);
        }

        $esperado = self::siguiente($remesa);

        if ($esperado === null) {
            throw ValidationException::withMessages(['evento' => 'Esta remesa ya tiene todos sus tiempos reportados.']);
        }

        if ($esperado !== [$punto, $evento]) {
            [$puntoEsperado, $eventoEsperado] = $esperado;
            $etiqueta = TiempoLogistico::EVENTOS[$eventoEsperado].' en '.mb_strtolower(TiempoLogistico::PUNTOS[$puntoEsperado]);

            throw ValidationException::withMessages(['evento' => "Lo siguiente por reportar es «{$etiqueta}»."]);
        }

        $tiempo = $remesa->tiemposLogisticos()->create([
            'empresa_id' => $remesa->empresa_id,
            'punto' => $punto,
            'evento' => $evento,
            'ocurrido_at' => $datos['ocurrido_at'] ?? now(),
            'latitud' => $datos['latitud'] ?? null,
            'longitud' => $datos['longitud'] ?? null,
            'conductor_id' => $conductor?->id,
            'user_id' => $usuario?->id,
            'observacion' => $datos['observacion'] ?? null,
        ]);

        self::volcarEnLaRemesa($remesa);

        return $tiempo;
    }

    /**
     * Copia los tiempos a las columnas de la remesa que ya viajan en el cumplido
     * (proceso 5), para no pedirle al usuario datos que el conductor ya marcó.
     */
    public static function volcarEnLaRemesa(RemesaCarga $remesa): void
    {
        $hechos = self::registrados($remesa);

        $remesa->forceFill(array_filter([
            'entrada_cargue' => $hechos['cargue.entrada']?->ocurrido_at ?? null,
            'salida_cargue' => $hechos['cargue.salida']?->ocurrido_at ?? null,
        ], fn ($v) => $v !== null))->saveQuietly();
    }

    /** Horas de espera en un punto: entre la llegada y la salida. */
    public static function espera(RemesaCarga $remesa, string $punto): ?float
    {
        $hechos = self::registrados($remesa);
        $llegada = $hechos["{$punto}.llegada"]?->ocurrido_at;
        $salida = $hechos["{$punto}.salida"]?->ocurrido_at;

        return ($llegada && $salida) ? round($llegada->floatDiffInHours($salida), 2) : null;
    }

    /**
     * Horas pactadas para ese punto, tal como se firmaron en la remesa.
     * Sirve para avisar cuando la espera real las superó: ese excedente se cobra.
     */
    public static function pactadas(RemesaCarga $remesa, string $punto): float
    {
        return $punto === 'cargue'
            ? (float) $remesa->horas_pacto_cargue + ((float) $remesa->minutos_pacto_cargue / 60)
            : (float) $remesa->horas_pacto_descargue + ((float) $remesa->minutos_pacto_descargue / 60);
    }

    /** Horas de más allá de lo pactado (0 si no se excedió o aún no hay datos). */
    public static function excedente(RemesaCarga $remesa, string $punto): float
    {
        $real = self::espera($remesa, $punto);

        return $real === null ? 0.0 : max(0.0, round($real - self::pactadas($remesa, $punto), 2));
    }
}
