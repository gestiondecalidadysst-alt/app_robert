<?php

namespace App\Actions\Mantenimiento;

use App\Enums\AccionMantenimiento;
use App\Enums\TipoMantenimiento;
use App\Models\Mantenimiento;
use App\Models\PlantillaMantenimiento;
use App\Models\RutinaMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Mantenimiento\PlanPreventivo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plan de mantenimiento de un vehículo:
 * - Seguir una plantilla (cambia si cambia la plantilla).
 * - Personalizarlo: rutinas propias (cambiar intervalos, quitar o agregar). Las rutinas propias que
 *   se quitan se desactivan, no se borran (conservan su historial de mantenimientos).
 * - Copiar el plan de otro vehículo.
 * - Punto de partida: cuándo y a cuántos km se hizo cada rutina por última vez (registro inicial,
 *   no cuenta como mantenimiento real).
 */
final class PlanDeVehiculo
{
    public static function seguirPlantilla(Vehiculo $vehiculo, ?PlantillaMantenimiento $plantilla): void
    {
        if ($plantilla && $plantilla->empresa_id !== $vehiculo->empresa_id) {
            throw ValidationException::withMessages(['plantilla_mantenimiento_id' => 'La plantilla no es de esta empresa.']);
        }

        DB::transaction(function () use ($vehiculo, $plantilla): void {
            $vehiculo->rutinasPropias()->update(['activo' => false]);
            $vehiculo->forceFill(['plantilla_mantenimiento_id' => $plantilla?->id, 'plan_personalizado' => false])->save();
        });
    }

    /**
     * @param  list<array{id?: ?int, nombre: string, intervalo_km?: mixed, intervalo_meses?: mixed, descripcion?: ?string}>  $rutinas
     */
    public static function personalizar(Vehiculo $vehiculo, array $rutinas): void
    {
        $limpias = collect($rutinas)->map(fn ($r) => [
            'id' => filled($r['id'] ?? null) ? (int) $r['id'] : null,
            'nombre' => trim((string) ($r['nombre'] ?? '')),
            'intervalo_km' => filled($r['intervalo_km'] ?? null) ? (int) $r['intervalo_km'] : null,
            'intervalo_meses' => filled($r['intervalo_meses'] ?? null) ? (int) $r['intervalo_meses'] : null,
            'descripcion' => filled($r['descripcion'] ?? null) ? trim($r['descripcion']) : null,
        ])->values();

        $errores = [];
        if ($limpias->isEmpty()) {
            $errores['rutinas'] = 'El plan debe tener al menos una rutina.';
        }
        foreach ($limpias as $i => $r) {
            if ($r['nombre'] === '') {
                $errores["rutinas.{$i}.nombre"] = 'Escriba el nombre de la rutina.';
            }
            if (! $r['intervalo_km'] && ! $r['intervalo_meses']) {
                $errores["rutinas.{$i}.intervalo_km"] = 'Indique cada cuántos km o meses.';
            }
        }
        $repetidas = $limpias->countBy(fn ($r) => PlanPreventivo::clave($r['nombre']))->filter(fn ($n) => $n > 1)->keys();
        if ($repetidas->isNotEmpty()) {
            $errores['rutinas'] = 'Hay rutinas con el mismo nombre.';
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        DB::transaction(function () use ($vehiculo, $limpias): void {
            $propias = $vehiculo->rutinasPropias()->get()->keyBy('id');
            $usadas = [];

            foreach ($limpias as $i => $r) {
                // Reutiliza la rutina propia con ese id, o una propia con el mismo nombre (conserva su historial).
                $rutina = ($r['id'] ? $propias->get($r['id']) : null)
                    ?? $propias->first(fn (RutinaMantenimiento $p) => PlanPreventivo::clave($p->nombre) === PlanPreventivo::clave($r['nombre']) && ! in_array($p->id, $usadas, true))
                    ?? new RutinaMantenimiento(['vehiculo_id' => $vehiculo->id]);

                $rutina->fill([
                    'vehiculo_id' => $vehiculo->id,
                    'plantilla_mantenimiento_id' => null,
                    'nombre' => $r['nombre'],
                    'intervalo_km' => $r['intervalo_km'],
                    'intervalo_meses' => $r['intervalo_meses'],
                    'descripcion' => $r['descripcion'],
                    'orden' => ($i + 1) * 10,
                    'activo' => true,
                ]);
                $rutina->empresa_id = $vehiculo->empresa_id;
                $rutina->save();
                $usadas[] = $rutina->id;
            }

            $vehiculo->rutinasPropias()->whereNotIn('id', $usadas)->update(['activo' => false]);
            $vehiculo->forceFill(['plan_personalizado' => true, 'plantilla_mantenimiento_id' => $vehiculo->plantilla_mantenimiento_id ?? PlanPreventivo::plantillaDe($vehiculo)?->id])->save();
        });
    }

    /** Copia el plan (rutinas e intervalos) de otro vehículo como plan personalizado. */
    public static function copiarDe(Vehiculo $destino, Vehiculo $origen): void
    {
        if ($origen->empresa_id !== $destino->empresa_id) {
            throw ValidationException::withMessages(['origen' => 'El vehículo no es de esta empresa.']);
        }

        self::personalizar($destino, PlanPreventivo::rutinasDe($origen)->map(fn (RutinaMantenimiento $r) => [
            'nombre' => $r->nombre, 'intervalo_km' => $r->intervalo_km, 'intervalo_meses' => $r->intervalo_meses, 'descripcion' => $r->descripcion,
        ])->all());
    }

    /**
     * Punto de partida del plan: la última vez que se hizo cada rutina. Crea un "registro inicial" por
     * cada fecha y km distintos; no cuenta como mantenimiento real (ni en informes ni en indicadores).
     *
     * @param  array<int, array{fecha?: mixed, kilometraje?: mixed}>  $puntos  por id de rutina del plan
     */
    public static function puntoDePartida(Vehiculo $vehiculo, array $puntos, ?User $usuario = null): int
    {
        $rutinas = PlanPreventivo::rutinasDe($vehiculo)->keyBy('id');
        $grupos = [];
        $errores = [];

        foreach ($puntos as $rutinaId => $p) {
            if (blank($p['fecha'] ?? null) && blank($p['kilometraje'] ?? null)) {
                continue;
            }
            if (! $rutinas->has((int) $rutinaId)) {
                $errores["puntos.{$rutinaId}"] = 'La rutina no es del plan de este vehículo.';

                continue;
            }
            $fecha = filled($p['fecha'] ?? null) ? Carbon::parse($p['fecha'])->startOfDay() : today();
            if ($fecha->gt(today())) {
                $errores["puntos.{$rutinaId}.fecha"] = 'La fecha no puede ser futura.';

                continue;
            }
            $km = filled($p['kilometraje'] ?? null) ? (int) $p['kilometraje'] : null;
            $grupos[$fecha->toDateString() . '|' . $km][] = (int) $rutinaId;
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return DB::transaction(function () use ($vehiculo, $grupos, $rutinas, $usuario): int {
            foreach ($grupos as $clave => $ids) {
                [$fecha, $km] = explode('|', $clave);
                $m = new Mantenimiento([
                    'vehiculo_id' => $vehiculo->id,
                    'fecha' => $fecha,
                    'kilometraje' => $km !== '' ? (int) $km : null,
                    'tipo' => TipoMantenimiento::Preventivo,
                    'accion' => AccionMantenimiento::Revision,
                    'actividad' => 'Punto de partida del plan de mantenimiento: ' . collect($ids)->map(fn ($id) => $rutinas[$id]->nombre)->implode(', ') . '.',
                    'user_id' => $usuario?->id,
                ]);
                $m->empresa_id = $vehiculo->empresa_id;
                $m->registro_inicial = true;
                $m->save();
                $m->rutinas()->sync($ids);
            }

            return count($grupos);
        });
    }
}
