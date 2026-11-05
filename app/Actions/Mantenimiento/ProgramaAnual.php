<?php

namespace App\Actions\Mantenimiento;

use App\Enums\EstadoActividad;
use App\Enums\EstadoPrograma;
use App\Models\ActividadMantenimiento;
use App\Models\Empresa;
use App\Models\Mantenimiento;
use App\Models\ProgramaMantenimiento;
use App\Models\RutinaMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Mantenimiento\PlanPreventivo;
use App\Support\Webhooks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Programa anual de mantenimiento preventivo (PESV, paso 17; indicador de la Supertransporte:
 * actividades programadas vs. ejecutadas por trimestre).
 *
 * - Generar: proyecta las actividades del año de cada vehículo de la flota según su plan, el último
 *   mantenimiento de cada rutina y sus km diarios (lo que ocurra primero: km o meses). En el año en
 *   curso empieza hoy. Queda en BORRADOR (se puede regenerar).
 * - Aprobar: queda como línea base (fecha_original), con quién elaboró y quién aprobó. Si había una
 *   versión aprobada del mismo año, queda REEMPLAZADA y sus actividades ejecutadas o canceladas pasan
 *   a la nueva versión (la trazabilidad del año no se pierde).
 * - Ejecución: al registrar un mantenimiento real con la rutina, se marca la actividad pendiente más
 *   cercana y se reajustan las siguientes de esa rutina (queda en el historial).
 * - Reprogramar y cancelar: con motivo; incorporar un vehículo nuevo al programa aprobado.
 */
final class ProgramaAnual
{
    /** Máximo de días de adelanto con que un mantenimiento ejecuta una actividad programada. */
    public const VENTANA_ADELANTO = 45;

    public static function generar(Empresa $empresa, int $anio, array $opciones = [], ?User $usuario = null): ProgramaMantenimiento
    {
        if ($anio < (int) today()->year || $anio > (int) today()->year + 1) {
            throw ValidationException::withMessages(['anio' => 'Se programa el año en curso o el siguiente.']);
        }

        return DB::transaction(function () use ($empresa, $anio, $opciones, $usuario) {
            $borrador = ProgramaMantenimiento::query()->deEmpresa($empresa)->where('anio', $anio)
                ->where('estado', EstadoPrograma::Borrador->value)->first();

            if ($borrador) {
                $borrador->actividades()->delete();
                $programa = $borrador;
            } else {
                $programa = new ProgramaMantenimiento([
                    'anio' => $anio,
                    'version' => (int) ProgramaMantenimiento::query()->deEmpresa($empresa)->where('anio', $anio)->max('version') + 1,
                    'estado' => EstadoPrograma::Borrador,
                ]);
                $programa->empresa_id = $empresa->id;
            }

            $programa->fill([
                'codigo_formato' => $opciones['codigo_formato'] ?? $programa->codigo_formato,
                'km_diarios_defecto' => (int) ($opciones['km_diarios_defecto'] ?? $empresa->km_diarios_defecto ?? 150),
                'observaciones' => $opciones['observaciones'] ?? $programa->observaciones,
            ]);
            $programa->elaborado_por = $usuario?->id;
            $programa->elaborado_at = now();
            $programa->save();

            Vehiculo::query()->deEmpresa($empresa)->enFlota()->orderBy('placa')->get()
                ->each(fn (Vehiculo $v) => self::crearActividades($programa, $v, today()->max(Carbon::create($anio, 1, 1))));

            return $programa->refresh();
        });
    }

    public static function aprobar(ProgramaMantenimiento $programa, ?User $usuario = null): void
    {
        if ($programa->estado !== EstadoPrograma::Borrador) {
            throw ValidationException::withMessages(['estado' => 'Solo se aprueba un programa en borrador.']);
        }
        if ($programa->actividades()->doesntExist()) {
            throw ValidationException::withMessages(['estado' => 'El programa no tiene actividades.']);
        }

        DB::transaction(function () use ($programa, $usuario): void {
            $anterior = ProgramaMantenimiento::query()->deEmpresa($programa->empresa_id)->where('anio', $programa->anio)
                ->where('estado', EstadoPrograma::Aprobado->value)->whereKeyNot($programa->id)->first();

            if ($anterior) {
                // Lo ya ejecutado o cancelado del año pasa a la versión nueva (trazabilidad del año completo).
                $anterior->actividades()->where('estado', '!=', EstadoActividad::Programada->value)
                    ->update(['programa_mantenimiento_id' => $programa->id]);
                $anterior->forceFill(['estado' => EstadoPrograma::Reemplazado])->save();
            }

            $programa->forceFill(['estado' => EstadoPrograma::Aprobado, 'aprobado_por' => $usuario?->id, 'aprobado_at' => now()])->save();
        });

        Webhooks::emitir($programa->empresa_id, 'mantenimiento.programa_aprobado', [
            'programa' => $programa->nombre,
            'anio' => $programa->anio,
            'version' => $programa->version,
            'actividades' => $programa->actividades()->count(),
            'aprobado_por' => $usuario?->name,
        ]);
    }

    /** Agrega al programa aprobado un vehículo que no estaba (p. ej. recién vinculado). */
    public static function incorporarVehiculo(ProgramaMantenimiento $programa, Vehiculo $vehiculo, ?User $usuario = null): int
    {
        if ($programa->estado === EstadoPrograma::Reemplazado) {
            throw ValidationException::withMessages(['estado' => 'El programa fue reemplazado por otra versión.']);
        }
        if ($programa->actividades()->where('vehiculo_id', $vehiculo->id)->pendientes()->exists()) {
            throw ValidationException::withMessages(['vehiculo_id' => "El vehículo {$vehiculo->placa} ya está en el programa."]);
        }

        return self::crearActividades($programa, $vehiculo, today()->max(Carbon::create($programa->anio, 1, 1)), $programa->aprobado(), $usuario);
    }

    public static function reprogramar(ActividadMantenimiento $actividad, mixed $fecha, ?string $motivo, ?User $usuario = null): void
    {
        self::exigirPendiente($actividad);
        $nueva = Carbon::parse($fecha)->startOfDay();
        if (blank($motivo)) {
            throw ValidationException::withMessages(['motivo' => 'Indique el motivo de la reprogramación.']);
        }
        if ($nueva->year !== $actividad->programa->anio) {
            throw ValidationException::withMessages(['fecha' => "La fecha debe ser del año {$actividad->programa->anio}."]);
        }

        $actividad->registrar('reprogramada', "De {$actividad->fecha_programada->format('d/m/Y')} a {$nueva->format('d/m/Y')}. Motivo: ".trim($motivo), $usuario);
        $actividad->fecha_programada = $nueva;
        $actividad->avisado_proximo_at = null;
        $actividad->avisado_vencido_at = null;
        $actividad->save();
    }

    public static function cancelar(ActividadMantenimiento $actividad, ?string $motivo, ?User $usuario = null): void
    {
        self::exigirPendiente($actividad);
        if (blank($motivo)) {
            throw ValidationException::withMessages(['motivo' => 'Indique el motivo de la cancelación.']);
        }

        $actividad->registrar('cancelada', trim($motivo), $usuario);
        $actividad->estado = EstadoActividad::Cancelada;
        $actividad->motivo = trim($motivo);
        $actividad->save();
    }

    /**
     * Un mantenimiento real con rutinas ejecuta la actividad pendiente más cercana de cada rutina
     * (hasta 45 días de adelanto) y reajusta las siguientes de esa rutina según lo ejecutado.
     */
    public static function registrarEjecucion(Mantenimiento $mantenimiento, ?User $usuario = null): int
    {
        if ($mantenimiento->registro_inicial || $mantenimiento->anulado()) {
            return 0;
        }

        $programa = self::programaVigente($mantenimiento->empresa_id, (int) $mantenimiento->fecha->year);
        if (! $programa) {
            return 0;
        }

        $vehiculo = Vehiculo::withTrashed()->find($mantenimiento->vehiculo_id);
        $ejecutadas = 0;

        foreach ($mantenimiento->rutinas as $rutina) {
            $clave = PlanPreventivo::clave($rutina->nombre);
            $pendientes = $programa->actividades()->where('vehiculo_id', $mantenimiento->vehiculo_id)->pendientes()
                ->orderBy('fecha_programada')->get()
                ->filter(fn (ActividadMantenimiento $a) => PlanPreventivo::clave($a->rutina_nombre) === $clave)->values();

            $actividad = $pendientes->first(fn (ActividadMantenimiento $a) => $a->fecha_programada->lte($mantenimiento->fecha->copy()->addDays(self::VENTANA_ADELANTO)));
            if (! $actividad) {
                continue;
            }

            $actividad->forceFill([
                'estado' => EstadoActividad::Ejecutada,
                'mantenimiento_id' => $mantenimiento->id,
                'fecha_ejecucion' => $mantenimiento->fecha->toDateString(),
                'km_ejecucion' => $mantenimiento->kilometraje,
            ]);
            $desviacion = $actividad->desviacionDias();
            $actividad->registrar('ejecutada', "Mantenimiento del {$mantenimiento->fecha->format('d/m/Y')}"
                .($mantenimiento->kilometraje !== null ? ' a '.number_format($mantenimiento->kilometraje, 0, ',', '.').' km' : '')
                .($desviacion ? ($desviacion > 0 ? " ({$desviacion} días tarde)" : ' ('.abs($desviacion).' días antes)') : ' (a tiempo)').'.', $usuario);
            $actividad->save();
            $ejecutadas++;

            // Las siguientes de la rutina se reajustan desde lo ejecutado.
            $siguientes = $pendientes->reject(fn ($a) => $a->id === $actividad->id)->values();
            if ($siguientes->isNotEmpty() && $vehiculo) {
                $rutinaPlan = PlanPreventivo::rutinasDe($vehiculo)->first(fn ($r) => PlanPreventivo::clave($r->nombre) === $clave) ?? $rutina;
                $proyeccion = self::proyectar($rutinaPlan, $mantenimiento->fecha, $mantenimiento->kilometraje,
                    PlanPreventivo::kmDiarios($vehiculo, $programa->km_diarios_defecto), $mantenimiento->fecha->copy()->addDay(), Carbon::create($programa->anio, 12, 31));

                foreach ($siguientes as $i => $siguiente) {
                    $nueva = $proyeccion[$i] ?? null;
                    if (! $nueva) {
                        $siguiente->registrar('cancelada', 'Ya no cabe en el año tras la ejecución real.', $usuario);
                        $siguiente->forceFill(['estado' => EstadoActividad::Cancelada, 'motivo' => 'Ajuste automático por ejecución real.'])->save();

                        continue;
                    }
                    if (! $nueva['fecha']->isSameDay($siguiente->fecha_programada)) {
                        $siguiente->registrar('ajuste', "Ajuste automático por la ejecución real: de {$siguiente->fecha_programada->format('d/m/Y')} a {$nueva['fecha']->format('d/m/Y')}.", $usuario);
                        $siguiente->forceFill(['fecha_programada' => $nueva['fecha']->toDateString(), 'km_programado' => $nueva['km'], 'avisado_proximo_at' => null, 'avisado_vencido_at' => null])->save();
                    }
                }
            }
        }

        return $ejecutadas;
    }

    /** Anular un mantenimiento devuelve sus actividades a "programada". */
    public static function revertirEjecucion(Mantenimiento $mantenimiento, ?User $usuario = null): void
    {
        ActividadMantenimiento::query()->deEmpresa($mantenimiento->empresa_id)->where('mantenimiento_id', $mantenimiento->id)->get()
            ->each(function (ActividadMantenimiento $a) use ($usuario): void {
                $a->registrar('revertida', 'Se anuló el mantenimiento que la ejecutaba.', $usuario);
                $a->forceFill(['estado' => EstadoActividad::Programada, 'mantenimiento_id' => null, 'fecha_ejecucion' => null, 'km_ejecucion' => null])->save();
            });
    }

    /** Programa aprobado del año (o el borrador si no hay aprobado). */
    public static function programaVigente(int $empresaId, int $anio): ?ProgramaMantenimiento
    {
        return ProgramaMantenimiento::query()->deEmpresa($empresaId)->where('anio', $anio)
            ->whereIn('estado', [EstadoPrograma::Aprobado->value, EstadoPrograma::Borrador->value])
            ->orderByRaw("case estado when 'aprobado' then 0 else 1 end")->orderByDesc('version')->first();
    }

    /**
     * Ocurrencias de una rutina entre $desde y $hasta, a partir de la última ejecución (fecha y km):
     * cada una cae en la primera fecha entre "km del intervalo / km diarios" y "meses del intervalo".
     * Si la próxima ya está vencida, se programa en $desde (año en curso). Para el año siguiente
     * ($recuperarAtrasada = false) se supone que lo de este año se hará a tiempo y el ciclo continúa.
     *
     * @return list<array{fecha: Carbon, km: ?int, atrasada: bool}>
     */
    public static function proyectar(RutinaMantenimiento $rutina, Carbon $baseFecha, ?int $baseKm, int $kmDia, Carbon $desde, Carbon $hasta, bool $recuperarAtrasada = true): array
    {
        if (! $rutina->intervalo_km && ! $rutina->intervalo_meses) {
            return [];
        }

        $resultado = [];
        $fecha = $baseFecha->copy()->startOfDay();
        $km = $baseKm;

        for ($i = 0; $i < 60; $i++) {
            $porKm = $rutina->intervalo_km ? $fecha->copy()->addDays((int) ceil($rutina->intervalo_km / max(1, $kmDia))) : null;
            $porMeses = $rutina->intervalo_meses ? $fecha->copy()->addMonthsNoOverflow($rutina->intervalo_meses) : null;
            $siguiente = collect([$porKm, $porMeses])->filter()->min();
            $kmSiguiente = $km !== null
                ? ($rutina->intervalo_km && $siguiente->equalTo($porKm) ? $km + $rutina->intervalo_km : $km + (int) round($fecha->diffInDays($siguiente) * $kmDia))
                : null;

            $atrasada = $siguiente->lt($desde);
            if ($atrasada && $resultado === [] && $recuperarAtrasada) {
                // Vencida antes de empezar: se programa al inicio y desde ahí sigue el ciclo.
                $siguiente = $desde->copy();
                $kmSiguiente = $km !== null ? $km + (int) round($fecha->diffInDays($siguiente) * $kmDia) : null;
            } elseif ($atrasada) {
                $fecha = $siguiente;
                $km = $kmSiguiente;

                continue;
            }

            if ($siguiente->gt($hasta)) {
                break;
            }

            $resultado[] = ['fecha' => $siguiente->copy(), 'km' => $kmSiguiente, 'atrasada' => $atrasada];
            $fecha = $siguiente;
            $km = $kmSiguiente;
        }

        return $resultado;
    }

    /** Proyecta y guarda las actividades de un vehículo en el programa. */
    private static function crearActividades(ProgramaMantenimiento $programa, Vehiculo $vehiculo, Carbon $desde, bool $incorporada = false, ?User $usuario = null): int
    {
        $hasta = Carbon::create($programa->anio, 12, 31);
        $kmDia = PlanPreventivo::kmDiarios($vehiculo, $programa->km_diarios_defecto);
        $kmActual = $vehiculo->kilometrajeActual();
        $plan = PlanPreventivo::para($vehiculo, $kmActual);
        $creadas = 0;

        foreach ($plan as $fila) {
            /** @var RutinaMantenimiento $rutina */
            $rutina = $fila['rutina'];
            $ultimo = $fila['ultimo'];
            // Sin historial: se toma hoy (y el km actual) como punto de partida.
            $baseFecha = $ultimo?->fecha ?? today();
            $baseKm = $ultimo?->kilometraje ?? $kmActual;

            foreach (self::proyectar($rutina, $baseFecha, $baseKm, $kmDia, $desde, $hasta, $desde->lte(today())) as $o) {
                $actividad = new ActividadMantenimiento([
                    'programa_mantenimiento_id' => $programa->id,
                    'vehiculo_id' => $vehiculo->id,
                    'rutina_mantenimiento_id' => $rutina->id,
                    'rutina_nombre' => $rutina->nombre,
                    'frecuencia' => $rutina->frecuencia,
                    'fecha_original' => $o['fecha']->toDateString(),
                    'km_original' => $o['km'],
                    'fecha_programada' => $o['fecha']->toDateString(),
                    'km_programado' => $o['km'],
                    'estado' => EstadoActividad::Programada,
                    'incorporada' => $incorporada,
                ]);
                $actividad->empresa_id = $programa->empresa_id;
                $actividad->registrar('programada', ($incorporada ? 'Incorporada al programa aprobado. ' : '')
                    .($o['atrasada'] ? 'Estaba vencida al programar. ' : '')
                    ."Base: {$baseFecha->format('d/m/Y')}".($baseKm !== null ? ' a '.number_format($baseKm, 0, ',', '.').' km' : '')
                    .($ultimo ? '' : ' (sin historial: se toma hoy)')."; {$kmDia} km/día.", $usuario);
                $actividad->save();
                $creadas++;
            }
        }

        return $creadas;
    }

    private static function exigirPendiente(ActividadMantenimiento $actividad): void
    {
        if ($actividad->estado !== EstadoActividad::Programada) {
            throw ValidationException::withMessages(['estado' => 'La actividad ya está '.mb_strtolower($actividad->estado->getLabel()).'.']);
        }
        if ($actividad->programa->estado === EstadoPrograma::Reemplazado) {
            throw ValidationException::withMessages(['estado' => 'El programa fue reemplazado por otra versión.']);
        }
    }

    /**
     * Indicadores por trimestre (por la fecha de la línea base):
     * - programadas: no canceladas; ejecutadas: con mantenimiento registrado.
     * - cumplimiento a la fecha: ejecutadas / (ejecutadas + pendientes cuya fecha ya pasó). Vacío si
     *   en ese trimestre aún no vence ninguna.
     */
    public static function indicadores(ProgramaMantenimiento $programa, ?int $vehiculoId = null): Collection
    {
        $actividades = $programa->actividades()->when($vehiculoId, fn ($q) => $q->where('vehiculo_id', $vehiculoId))->get();

        return collect([1, 2, 3, 4])->mapWithKeys(function (int $t) use ($actividades) {
            $delTrimestre = $actividades->filter(fn (ActividadMantenimiento $a) => (int) ceil($a->fecha_original->month / 3) === $t);
            $programadas = $delTrimestre->where('estado', '!=', EstadoActividad::Cancelada)->count();
            $ejecutadas = $delTrimestre->where('estado', EstadoActividad::Ejecutada)->count();
            $vencidas = $delTrimestre->filter(fn (ActividadMantenimiento $a) => $a->estado === EstadoActividad::Programada && $a->fecha_original->lt(today()))->count();
            $exigibles = $ejecutadas + $vencidas;

            return ["T{$t}" => [
                'programadas' => $programadas,
                'ejecutadas' => $ejecutadas,
                'canceladas' => $delTrimestre->where('estado', EstadoActividad::Cancelada)->count(),
                'exigibles' => $exigibles,
                'cumplimiento' => $exigibles ? round($ejecutadas * 100 / $exigibles, 1) : null,
            ]];
        });
    }
}
