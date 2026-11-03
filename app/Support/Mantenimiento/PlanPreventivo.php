<?php

namespace App\Support\Mantenimiento;

use App\Enums\CriterioPlantilla;
use App\Models\Empresa;
use App\Models\Inspeccion;
use App\Models\Mantenimiento;
use App\Models\PlantillaMantenimiento;
use App\Models\RutinaMantenimiento;
use App\Models\Vehiculo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Plan preventivo de un vehículo: cada rutina aplicable con su último mantenimiento, el próximo
 * (por km y por fecha, lo que ocurra primero) y su semáforo.
 *
 * - Rutinas: las del plan personalizado del vehículo o las de su plantilla (rutinasDe).
 * - Kilometraje actual: Vehiculo::kilometrajeActual() (preoperacionales, inspecciones y mantenimientos).
 * - Estados: vencido (rojo) · próximo (amarillo: faltan menos de 1.000 km o 15 días) · al día (verde)
 *   · sin registro (gris: no hay un mantenimiento de esa rutina para tomar como punto de partida).
 * - Un vencimiento solo alerta y abre un plan de acción: no bloquea el FUEC (decisión del usuario).
 */
final class PlanPreventivo
{
    public const AVISO_KM = 1000;

    public const AVISO_DIAS = 15;

    public const VENCIDO = 'vencido';

    public const PROXIMO = 'proximo';

    public const AL_DIA = 'al_dia';

    public const SIN_REGISTRO = 'sin_registro';

    public const ETIQUETAS = [
        self::VENCIDO => 'Vencido',
        self::PROXIMO => 'Próximo',
        self::AL_DIA => 'Al día',
        self::SIN_REGISTRO => 'Sin registro',
    ];

    public const COLORES = [
        self::VENCIDO => 'danger',
        self::PROXIMO => 'warning',
        self::AL_DIA => 'success',
        self::SIN_REGISTRO => 'gray',
    ];

    /**
     * Rutinas del plan del vehículo:
     * - Plan personalizado: sus propias rutinas activas.
     * - Si no: las de su plantilla (asignada o sugerida por clase, marca/línea o la por defecto).
     *
     * @return Collection<int, RutinaMantenimiento>
     */
    public static function rutinasDe(Vehiculo $vehiculo): Collection
    {
        if ($vehiculo->plan_personalizado) {
            return RutinaMantenimiento::query()->deEmpresa($vehiculo->empresa_id)->activas()
                ->where('vehiculo_id', $vehiculo->id)->orderBy('orden')->orderBy('id')->get();
        }

        $plantilla = self::plantillaDe($vehiculo);

        return $plantilla
            ? RutinaMantenimiento::query()->deEmpresa($vehiculo->empresa_id)->activas()
                ->where('plantilla_mantenimiento_id', $plantilla->id)->orderBy('orden')->orderBy('id')->get()
            : collect();
    }

    /** Plantilla que sigue el vehículo: la asignada (si está activa) o la sugerida. */
    public static function plantillaDe(Vehiculo $vehiculo): ?PlantillaMantenimiento
    {
        if ($vehiculo->plantilla_mantenimiento_id) {
            $asignada = PlantillaMantenimiento::query()->deEmpresa($vehiculo->empresa_id)->activas()->find($vehiculo->plantilla_mantenimiento_id);
            if ($asignada) {
                return $asignada;
            }
        }

        return self::plantillaSugerida($vehiculo);
    }

    /**
     * Plantilla que corresponde al vehículo por sus datos, de la más específica a la más general:
     * marca y línea → marca (todas las líneas) → clase de vehículo → la por defecto.
     */
    public static function plantillaSugerida(Vehiculo $vehiculo): ?PlantillaMantenimiento
    {
        $plantillas = PlantillaMantenimiento::query()->deEmpresa($vehiculo->empresa_id)->activas()->get();

        return $plantillas->first(fn ($p) => $p->criterio === CriterioPlantilla::MarcaLinea && $p->marca_id && $p->marca_id === $vehiculo->marca_id && $p->linea_id && $p->linea_id === $vehiculo->linea_id)
            ?? $plantillas->first(fn ($p) => $p->criterio === CriterioPlantilla::MarcaLinea && $p->marca_id && $p->marca_id === $vehiculo->marca_id && ! $p->linea_id)
            ?? $plantillas->first(fn ($p) => $p->criterio === CriterioPlantilla::Clase && $p->clase_vehiculo_id && $p->clase_vehiculo_id === $vehiculo->clase_vehiculo_id)
            ?? $plantillas->firstWhere('por_defecto', true);
    }

    /**
     * Kilómetros que recorre el vehículo al día: del kilometraje reportado en los últimos 90 días
     * (preoperacionales, inspecciones y mantenimientos). Si no hay datos suficientes, el de la empresa.
     */
    public static function kmDiarios(Vehiculo $vehiculo, ?int $defecto = null): int
    {
        $desde = today()->subDays(90);
        $lecturas = Inspeccion::query()->deEmpresa($vehiculo->empresa_id)->where('vehiculo_id', $vehiculo->id)
            ->whereNotNull('kilometraje')->whereDate('fecha', '>=', $desde)->get(['fecha', 'kilometraje'])->toBase()
            ->map(fn ($i) => [$i->fecha, $i->kilometraje])
            ->merge(Mantenimiento::query()->deEmpresa($vehiculo->empresa_id)->vigentes()->where('vehiculo_id', $vehiculo->id)
                ->whereNotNull('kilometraje')->whereDate('fecha', '>=', $desde)->get(['fecha', 'kilometraje'])->toBase()
                ->map(fn ($m) => [$m->fecha, $m->kilometraje]))
            ->sortBy(fn ($l) => $l[0]->timestamp)->values();

        if ($lecturas->count() >= 2) {
            $dias = $lecturas->first()[0]->diffInDays($lecturas->last()[0]);
            $km = $lecturas->max(fn ($l) => $l[1]) - $lecturas->min(fn ($l) => $l[1]);
            if ($dias >= 7 && $km > 0) {
                return max(1, (int) round($km / $dias));
            }
        }

        return max(1, $defecto ?? (int) (Empresa::query()->whereKey($vehiculo->empresa_id)->value('km_diarios_defecto') ?: 150));
    }

    /**
     * @return Collection<int, array{rutina: RutinaMantenimiento, ultimo: ?Mantenimiento, km_actual: ?int, proximo_km: ?int,
     *                                proxima_fecha: ?Carbon, faltan_km: ?int, faltan_dias: ?int, estado: string}>
     */
    public static function para(Vehiculo $vehiculo, ?int $kmActual = null): Collection
    {
        $kmActual ??= $vehiculo->kilometrajeActual();

        // Último mantenimiento por nombre de rutina (cuenta aunque se haya hecho con la rutina general).
        $ultimos = [];
        Mantenimiento::query()->deEmpresa($vehiculo->empresa_id)->vigentes()->where('vehiculo_id', $vehiculo->id)
            ->with('rutinas')->orderByDesc('fecha')->orderByDesc('id')->get()
            ->each(function (Mantenimiento $m) use (&$ultimos): void {
                foreach ($m->rutinas as $r) {
                    $ultimos[self::clave($r->nombre)] ??= $m;
                }
            });

        return self::rutinasDe($vehiculo)->map(function (RutinaMantenimiento $rutina) use ($ultimos, $kmActual) {
            $ultimo = $ultimos[self::clave($rutina->nombre)] ?? null;
            $proximoKm = $ultimo?->kilometraje !== null && $rutina->intervalo_km ? $ultimo->kilometraje + $rutina->intervalo_km : null;
            $proximaFecha = $ultimo && $rutina->intervalo_meses ? $ultimo->fecha->copy()->addMonthsNoOverflow($rutina->intervalo_meses) : null;
            $faltanKm = $proximoKm !== null && $kmActual !== null ? $proximoKm - $kmActual : null;
            $faltanDias = $proximaFecha ? (int) today()->diffInDays($proximaFecha, false) : null;

            $estado = match (true) {
                ! $ultimo => self::SIN_REGISTRO,
                ($faltanKm !== null && $faltanKm <= 0) || ($faltanDias !== null && $faltanDias <= 0) => self::VENCIDO,
                ($faltanKm !== null && $faltanKm < self::AVISO_KM) || ($faltanDias !== null && $faltanDias < self::AVISO_DIAS) => self::PROXIMO,
                default => self::AL_DIA,
            };

            return [
                'rutina' => $rutina,
                'ultimo' => $ultimo,
                'km_actual' => $kmActual,
                'proximo_km' => $proximoKm,
                'proxima_fecha' => $proximaFecha,
                'faltan_km' => $faltanKm,
                'faltan_dias' => $faltanDias,
                'estado' => $estado,
            ];
        });
    }

    /** Peor estado del vehículo (para el listado). */
    public static function resumen(Collection $plan): string
    {
        foreach ([self::VENCIDO, self::PROXIMO, self::SIN_REGISTRO, self::AL_DIA] as $estado) {
            if ($plan->contains('estado', $estado)) {
                return $estado;
            }
        }

        return self::SIN_REGISTRO;
    }

    public static function clave(string $nombre): string
    {
        return Str::lower(Str::ascii(trim($nombre)));
    }

    /** "Faltan 820 km · 40 días" / "Vencido hace 300 km" */
    public static function textoFaltante(array $fila): string
    {
        $partes = [];
        if ($fila['faltan_km'] !== null) {
            $partes[] = $fila['faltan_km'] > 0
                ? 'faltan ' . number_format($fila['faltan_km'], 0, ',', '.') . ' km'
                : 'pasado ' . number_format(abs($fila['faltan_km']), 0, ',', '.') . ' km';
        }
        if ($fila['faltan_dias'] !== null) {
            $partes[] = $fila['faltan_dias'] > 0 ? 'faltan ' . $fila['faltan_dias'] . ' días' : 'vencido hace ' . abs($fila['faltan_dias']) . ' días';
        }

        return ucfirst(implode(' · ', $partes)) ?: '—';
    }
}
