<?php

namespace App\Support\Operacion;

use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\Servicio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Control de jornada y fatiga del planeador (PESV, paso 8: programa de prevención de la fatiga;
 * paso 15: tiempos de conducción y descanso). Solo genera AVISOS (decisión del usuario): nunca
 * impide planificar ni expedir.
 *
 * Con la política de la empresa (Integraciones → Planeador de servicios):
 * - Conducción del día: suma de la conducción estimada de los servicios del conductor ese día.
 * - Conducción continua: un servicio más largo que el máximo sin una parada segura en su ruta.
 * - Descanso entre jornadas: horas entre la última llegada de un día y la primera salida del siguiente.
 * - Cruces: el vehículo o el conductor en dos servicios planificados a la misma hora.
 */
final class Jornada
{
    private const MAX_DIAS = 31;

    /**
     * @param  list<int>  $conductorIds
     * @return list<string>
     */
    public static function avisos(Servicio $servicio, array $conductorIds): array
    {
        $empresa = Empresa::findOrFail($servicio->empresa_id);
        $maxDia = (int) $empresa->jornada_max_horas_dia * 60;
        $maxContinua = (int) $empresa->jornada_max_horas_continuas * 60;
        $descanso = (int) $empresa->jornada_descanso_horas * 60;
        $avisos = [];

        $min = $servicio->minutosConduccion();
        if ($maxContinua && $min && $min > $maxContinua && ! $servicio->ruta?->tieneParadas()) {
            $avisos[] = 'Conducción continua estimada de '.self::horas($min).' (máximo '.self::horas($maxContinua)
                .' sin pausa): planee una parada segura en la ruta o un segundo conductor.';
        }

        $conductorIds = array_values(array_unique(array_map('intval', array_filter($conductorIds))));
        $dias = self::dias($servicio);
        if ($dias === [] || ! $servicio->fecha_inicio) {
            return $avisos;
        }

        $desde = $dias[0]->copy()->subDay();
        $hasta = end($dias)->copy()->addDay();
        $otros = Servicio::query()->deEmpresa($servicio->empresa_id)->vigentes()->entre($desde, $hasta)
            ->when($servicio->exists, fn ($q) => $q->whereKeyNot($servicio->getKey()))
            ->with(['conductores', 'ruta', 'vehiculo'])->get();

        // Cruces con otros servicios (el FUEC los bloquea al expedir; aquí se avisa desde el plan).
        foreach ($otros as $otro) {
            if (! $otro->horario()->seCruzaCon($servicio->horario())) {
                continue;
            }
            if ($servicio->vehiculo_id && $otro->vehiculo_id === (int) $servicio->vehiculo_id) {
                $avisos[] = "El vehículo {$otro->placa} ya tiene el servicio {$otro->ruta_texto} ({$otro->horario()->texto()}).";
            }
            foreach ($otro->conductores->whereIn('id', $conductorIds) as $c) {
                $avisos[] = "{$c->nombre_completo} ya tiene el servicio {$otro->ruta_texto} ({$otro->horario()->texto()}).";
            }
        }

        $nombres = Conductor::query()->deEmpresa($servicio->empresa_id)->withTrashed()->whereKey($conductorIds)->get()->keyBy('id');

        foreach ($conductorIds as $id) {
            $suyos = $otros->filter(fn (Servicio $o) => $o->conductores->contains('id', $id))->push($servicio);
            $nombre = $nombres->get($id)?->nombre_completo ?? 'El conductor';
            $excesos = [];
            $descansos = [];

            foreach ($dias as $dia) {
                $total = $suyos->filter(fn (Servicio $s) => $s->activoEl($dia))->sum(fn (Servicio $s) => $s->minutosConduccion() ?? 0);
                if ($maxDia && $total > $maxDia) {
                    $excesos[] = $dia->format('d/m').' ('.self::horas($total).')';
                }

                if ($descanso) {
                    $finAnterior = $suyos->filter(fn (Servicio $s) => $s->activoEl($dia->copy()->subDay()))
                        ->map(fn (Servicio $s) => self::intervalo($s, $dia->copy()->subDay())[1] ?? null)->filter()->max();
                    $inicioHoy = $suyos->filter(fn (Servicio $s) => $s->activoEl($dia))
                        ->map(fn (Servicio $s) => self::intervalo($s, $dia)[0] ?? null)->filter()->min();

                    if ($finAnterior && $inicioHoy && $finAnterior->lt($inicioHoy) && $finAnterior->diffInMinutes($inicioHoy) < $descanso) {
                        $descansos[] = $dia->format('d/m').' ('.self::horas((int) $finAnterior->diffInMinutes($inicioHoy)).')';
                    }
                }
            }

            if ($excesos) {
                $avisos[] = "{$nombre} supera las ".self::horas($maxDia).' de conducción al día: '.self::lista($excesos).'.';
            }
            if ($descansos) {
                $avisos[] = "{$nombre} descansa menos de ".self::horas($descanso).' entre jornadas: '.self::lista($descansos).'.';
            }
        }

        return array_values(array_unique($avisos));
    }

    /**
     * Minutos de conducción estimados por conductor y día en un rango (para el tablero por conductor).
     *
     * @param  Collection<int, Servicio>  $servicios  con conductores y ruta cargados
     * @return array<int, array<string, int>> [conductor_id][Y-m-d] => minutos
     */
    public static function minutosPorConductor(Collection $servicios, Carbon $desde, Carbon $hasta): array
    {
        $resultado = [];
        foreach ($servicios as $s) {
            for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
                if (! $s->activoEl($d)) {
                    continue;
                }
                foreach ($s->conductores as $c) {
                    $resultado[$c->id][$d->toDateString()] = ($resultado[$c->id][$d->toDateString()] ?? 0) + ($s->minutosConduccion() ?? 0);
                }
            }
        }

        return $resultado;
    }

    /** @return list<Carbon> */
    private static function dias(Servicio $s): array
    {
        if (! $s->fecha_inicio || ! $s->fecha_fin || $s->fecha_fin->lt($s->fecha_inicio)) {
            return [];
        }

        $dias = [];
        for ($d = $s->fecha_inicio->copy()->startOfDay(); $d->lte($s->fecha_fin) && count($dias) < self::MAX_DIAS; $d->addDay()) {
            $dias[] = $d->copy();
        }

        return $dias;
    }

    /** [salida, llegada] del servicio ese día; null si no tiene hora de salida (todo el día). */
    private static function intervalo(Servicio $s, Carbon $dia): ?array
    {
        if (! $s->hora_salida) {
            return null;
        }

        $inicio = $dia->copy()->setTimeFromTimeString($s->hora_salida);
        if ($s->hora_llegada) {
            $fin = $dia->copy()->setTimeFromTimeString($s->hora_llegada);
            if ($fin->lte($inicio)) {
                $fin->addDay();
            }
        } else {
            $fin = $inicio->copy()->addMinutes($s->minutosConduccion() ?? 0);
        }

        return [$inicio, $fin];
    }

    public static function horas(int $minutos): string
    {
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;

        return match (true) {
            $h === 0 => "{$m} min",
            $m === 0 => "{$h} h",
            default => "{$h} h {$m} min",
        };
    }

    private static function lista(array $items): string
    {
        return count($items) > 6 ? implode(', ', array_slice($items, 0, 6)).' y '.(count($items) - 6).' días más' : implode(', ', $items);
    }
}
