<?php

namespace App\Support\Sistema;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Lectura de la cola en base de datos (tablas jobs y failed_jobs). */
final class EstadoColas
{
    /** @return Collection<string, array{pendientes:int, mas_antiguo:?Carbon}> por fila */
    public static function pendientes(): Collection
    {
        $filas = DB::table('jobs')->selectRaw('queue, count(*) as total, min(created_at) as antiguo')->groupBy('queue')->get()->keyBy('queue');

        return collect(array_unique([...Colas::ORDEN, ...$filas->keys()->all()]))->mapWithKeys(fn ($q) => [$q => [
            'pendientes' => (int) ($filas[$q]->total ?? 0),
            'mas_antiguo' => isset($filas[$q]) ? Carbon::createFromTimestamp($filas[$q]->antiguo)->timezone(config('app.timezone')) : null,
        ]]);
    }

    /** Trabajos esperando desde hace más de $minutos (ya deberían haberse procesado). */
    public static function atascados(int $minutos = Latidos::MINUTOS_CAIDO): int
    {
        return DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<', now()->subMinutes($minutos)->getTimestamp())->count();
    }

    public static function fallidos(): int
    {
        return DB::table('failed_jobs')->count();
    }

    /** Nombre legible del trabajo (EnviarSms, EnviarWebhook…). */
    public static function nombreTrabajo(string $payload): string
    {
        $datos = json_decode($payload, true);

        return class_basename($datos['displayName'] ?? 'Trabajo');
    }
}
