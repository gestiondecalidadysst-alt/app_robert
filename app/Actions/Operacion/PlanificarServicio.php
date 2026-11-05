<?php

namespace App\Actions\Operacion;

use App\Enums\EstadoServicio;
use App\Models\Conductor;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\Fuec;
use App\Models\Municipio;
use App\Models\Ruta;
use App\Models\Servicio;
use App\Models\Vehiculo;
use App\Support\Webhooks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Planeador de servicios: crear o editar el plan, enlazarlo con el FUEC y cancelarlo.
 *
 * - El plan es libre: puede ir antes o después del extracto y sin vehículo ni conductor todavía.
 * - Los avisos de jornada y cruces no bloquean (App\Support\Operacion\Jornada).
 * - Con extracto, los datos del FUEC (vehículo, conductores, fechas, horas, origen y destino) mandan;
 *   del plan solo se cambian la ruta, la conducción estimada, los pasajeros y las observaciones.
 */
class PlanificarServicio
{
    /**
     * @param  array{contrato_id?:?int, ruta_id?:?int, vehiculo_id?:?int, conductores?:list<int>, fecha_inicio:string,
     *               fecha_fin?:?string, hora_salida?:?string, hora_llegada?:?string, origen_municipio_id:int,
     *               destino_municipio_id:int, recorrido?:?string, conduccion_min?:?int, pasajeros?:?int, observaciones?:?string}  $datos
     */
    public function guardar(Empresa $empresa, array $datos, ?Servicio $servicio = null): Servicio
    {
        if ($servicio?->cancelado()) {
            throw ValidationException::withMessages(['fecha_inicio' => 'El servicio está cancelado.']);
        }

        $conExtracto = (bool) $servicio?->fuec_id;
        $editables = ['ruta_id', 'conduccion_min', 'pasajeros', 'observaciones'];
        $datos = $conExtracto ? array_intersect_key($datos, array_flip($editables)) : $datos;

        $this->validar($empresa, $datos, $conExtracto);

        return DB::transaction(function () use ($empresa, $datos, $servicio, $conExtracto) {
            $servicio ??= new Servicio(['empresa_id' => $empresa->id, 'estado' => EstadoServicio::Planificado, 'user_id' => auth()->id()]);
            $servicio->empresa_id = $empresa->id;

            $valores = collect($datos)->only([
                'contrato_id', 'ruta_id', 'vehiculo_id', 'fecha_inicio', 'fecha_fin', 'hora_salida', 'hora_llegada',
                'origen_municipio_id', 'destino_municipio_id', 'recorrido', 'conduccion_min', 'pasajeros', 'observaciones',
            ])->map(fn ($v) => $v === '' ? null : $v)->all();

            if (! $conExtracto) {
                $valores['fecha_fin'] = $valores['fecha_fin'] ?? $valores['fecha_inicio'];
                $valores['hora_salida'] = self::hora($valores['hora_salida'] ?? null);
                $valores['hora_llegada'] = self::hora($valores['hora_llegada'] ?? null);
                // Sin rutograma elegido: el que corresponde al origen y destino.
                if (! array_key_exists('ruta_id', $datos)) {
                    $valores['ruta_id'] = Ruta::sugerida($empresa->id, (int) $valores['origen_municipio_id'], (int) $valores['destino_municipio_id'])?->id;
                }
            }

            $servicio->fill($valores)->save();

            if (! $conExtracto && array_key_exists('conductores', $datos)) {
                $servicio->conductores()->sync(self::conOrden($datos['conductores']));
            }

            return $servicio->refresh();
        });
    }

    /**
     * Enlaza un FUEC recién expedido con su servicio: el planificado desde el que se generó o uno
     * nuevo (así todo extracto aparece en el planeador con su vehículo).
     */
    public function desdeFuec(Fuec $fuec, ?int $servicioId = null): Servicio
    {
        $servicio = $servicioId
            ? Servicio::query()->deEmpresa($fuec->empresa_id)->whereNull('fuec_id')->find($servicioId)
            : null;

        $servicio ??= new Servicio([
            'empresa_id' => $fuec->empresa_id,
            'user_id' => auth()->id(),
            'ruta_id' => Ruta::sugerida($fuec->empresa_id, $fuec->origen_municipio_id, $fuec->destino_municipio_id)?->id,
        ]);

        $servicio->forceFill([
            'empresa_id' => $fuec->empresa_id,
            'fuec_id' => $fuec->id,
            'contrato_id' => $fuec->contrato_id,
            'vehiculo_id' => $fuec->vehiculo_id,
            'fecha_inicio' => $fuec->fecha_inicio,
            'fecha_fin' => $fuec->fecha_fin,
            'hora_salida' => $fuec->hora_inicio,
            'hora_llegada' => $fuec->hora_fin,
            'origen_municipio_id' => $fuec->origen_municipio_id,
            'destino_municipio_id' => $fuec->destino_municipio_id,
            'recorrido' => $fuec->recorrido ?? $servicio->recorrido,
            'estado' => in_array($servicio->estado, [EstadoServicio::EnCurso, EstadoServicio::Finalizado], true) ? $servicio->estado : EstadoServicio::ConExtracto,
        ])->save();

        $servicio->conductores()->sync(
            $fuec->conductores()->get()->mapWithKeys(fn (Conductor $c) => [$c->id => ['orden' => $c->pivot->orden]])->all()
        );

        return $servicio;
    }

    /** Cancela un servicio sin extracto vigente (con extracto: se anula el FUEC y el servicio se cancela solo). */
    public function cancelar(Servicio $servicio, string $motivo, bool $porAnulacion = false): Servicio
    {
        if ($servicio->cancelado()) {
            return $servicio;
        }
        if (! $porAnulacion && $servicio->fuec_id && ! $servicio->fuec?->anulado()) {
            throw ValidationException::withMessages(['motivo' => 'El servicio tiene extracto vigente: anule el FUEC y el servicio se cancela solo.']);
        }
        if (mb_strlen(trim($motivo)) < 5) {
            throw ValidationException::withMessages(['motivo' => 'Escriba el motivo de la cancelación.']);
        }

        $servicio->forceFill(['estado' => EstadoServicio::Cancelado, 'motivo_cancelacion' => trim($motivo)])->save();
        $servicio->registrar('cancelado', ['nota' => trim($motivo)]);

        return $servicio;
    }

    /**
     * Seguimiento del conductor desde el portal (opcional): leido | inicio | parada | novedad | fin.
     */
    public function reportar(Servicio $servicio, Conductor $conductor, string $tipo, array $datos = []): Servicio
    {
        if ($servicio->cancelado()) {
            throw ValidationException::withMessages(['tipo' => 'El servicio está cancelado.']);
        }
        if (! array_key_exists($tipo, ['leido' => 1, 'inicio' => 1, 'parada' => 1, 'novedad' => 1, 'fin' => 1])) {
            throw ValidationException::withMessages(['tipo' => 'Reporte no válido.']);
        }
        if ($tipo === 'novedad' && blank($datos['nota'] ?? null)) {
            throw ValidationException::withMessages(['nota' => 'Describa la novedad.']);
        }

        $km = filled($datos['kilometraje'] ?? null) ? (int) $datos['kilometraje'] : null;
        if ($tipo === 'fin' && $km !== null && $servicio->km_inicial !== null && $km < $servicio->km_inicial) {
            throw ValidationException::withMessages(['kilometraje' => "El kilometraje final no puede ser menor que el inicial ({$servicio->km_inicial})."]);
        }

        $lat = is_numeric($datos['latitud'] ?? null) && abs((float) $datos['latitud']) <= 90 ? (float) $datos['latitud'] : null;
        $lng = is_numeric($datos['longitud'] ?? null) && abs((float) $datos['longitud']) <= 180 ? (float) $datos['longitud'] : null;

        DB::transaction(function () use ($servicio, $conductor, $tipo, $datos, $km, $lat, $lng): void {
            $servicio->registrar($tipo, [
                'conductor_id' => $conductor->id,
                'user_id' => auth()->id(),
                'nota' => filled($datos['nota'] ?? null) ? mb_substr(trim($datos['nota']), 0, 1000) : null,
                'kilometraje' => $km,
                'latitud' => $lat !== null && $lng !== null ? $lat : null,
                'longitud' => $lat !== null && $lng !== null ? $lng : null,
            ]);

            match ($tipo) {
                'leido' => $servicio->leido_at ? null : $servicio->forceFill(['leido_at' => now(), 'leido_por' => $conductor->id])->save(),
                'inicio' => $servicio->forceFill(['estado' => EstadoServicio::EnCurso, 'iniciado_at' => $servicio->iniciado_at ?? now(), 'km_inicial' => $km ?? $servicio->km_inicial])->save(),
                'fin' => $servicio->forceFill(['estado' => EstadoServicio::Finalizado, 'finalizado_at' => now(), 'km_final' => $km ?? $servicio->km_final])->save(),
                default => null,
            };
        });

        if ($tipo === 'novedad') {
            Webhooks::emitir($servicio->empresa_id, 'servicio.novedad', [
                'servicio_id' => $servicio->id,
                'placa' => $servicio->placa,
                'ruta' => $servicio->ruta_texto,
                'conductor' => $conductor->nombre_completo,
                'telefono_conductor' => $conductor->telefono,
                'novedad' => $datos['nota'],
                'latitud' => $lat,
                'longitud' => $lng,
            ]);
        }

        return $servicio->refresh();
    }

    private function validar(Empresa $empresa, array $datos, bool $conExtracto): void
    {
        $errores = [];

        if (! $conExtracto) {
            $inicio = filled($datos['fecha_inicio'] ?? null) ? Carbon::parse($datos['fecha_inicio']) : null;
            $fin = filled($datos['fecha_fin'] ?? null) ? Carbon::parse($datos['fecha_fin']) : $inicio;

            if (! $inicio) {
                $errores['fecha_inicio'] = 'Indique la fecha del servicio.';
            } elseif ($fin && $fin->lt($inicio)) {
                $errores['fecha_fin'] = 'La fecha final no puede ser anterior a la inicial.';
            }
            foreach (['origen_municipio_id' => 'origen', 'destino_municipio_id' => 'destino'] as $campo => $nombre) {
                if (blank($datos[$campo] ?? null) || ! Municipio::query()->whereKey($datos[$campo])->exists()) {
                    $errores[$campo] = "Indique el municipio de {$nombre}.";
                }
            }
            if (filled($datos['contrato_id'] ?? null) && ! Contrato::query()->deEmpresa($empresa)->noAnulados()->whereKey($datos['contrato_id'])->exists()) {
                $errores['contrato_id'] = 'El contrato no existe o está anulado.';
            }
            if (filled($datos['vehiculo_id'] ?? null) && ! Vehiculo::query()->deEmpresa($empresa)->whereKey($datos['vehiculo_id'])->exists()) {
                $errores['vehiculo_id'] = 'El vehículo no pertenece a esta empresa.';
            }
            $ids = array_values(array_filter((array) ($datos['conductores'] ?? []), 'filled'));
            if (count($ids) > ExpedirFuec::MAX_CONDUCTORES) {
                $errores['conductores'] = 'Máximo '.ExpedirFuec::MAX_CONDUCTORES.' conductores.';
            } elseif (count($ids) !== count(array_unique($ids))) {
                $errores['conductores'] = 'Hay un conductor repetido.';
            } elseif ($ids && Conductor::query()->deEmpresa($empresa)->whereKey($ids)->count() !== count($ids)) {
                $errores['conductores'] = 'Algún conductor no pertenece a esta empresa.';
            }
        }

        if (filled($datos['ruta_id'] ?? null) && ! Ruta::query()->deEmpresa($empresa)->whereKey($datos['ruta_id'])->exists()) {
            $errores['ruta_id'] = 'El rutograma no pertenece a esta empresa.';
        }
        if (filled($datos['conduccion_min'] ?? null) && ((int) $datos['conduccion_min'] < 1 || (int) $datos['conduccion_min'] > 1440)) {
            $errores['conduccion_min'] = 'La conducción estimada debe estar entre 1 y 1.440 minutos.';
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    /** @return array<int, array{orden:int}> */
    private static function conOrden(array $ids): array
    {
        return collect(array_values(array_filter($ids, 'filled')))->mapWithKeys(fn ($id, $i) => [(int) $id => ['orden' => $i + 1]])->all();
    }

    private static function hora(mixed $valor): ?string
    {
        return filled($valor) && preg_match('/^(\d{1,2}):(\d{2})/', (string) $valor, $m) ? sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]) : null;
    }
}
