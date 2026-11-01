<?php

namespace App\Support\Mantenimiento;

use App\Models\Mantenimiento;
use App\Models\RutinaMantenimiento;
use App\Models\Vehiculo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Plan preventivo de un vehículo: cada rutina aplicable con su último mantenimiento, el próximo
 * (por km y por fecha, lo que ocurra primero) y su semáforo.
 *
 * - Rutinas aplicables: las de toda la flota, las de su clase y las del vehículo. Si comparten nombre,
 *   gana la más específica (vehículo > clase > flota).
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

    /** @return Collection<int, RutinaMantenimiento> */
    public static function rutinasDe(Vehiculo $vehiculo): Collection
    {
        return RutinaMantenimiento::query()->deEmpresa($vehiculo->empresa_id)->activas()
            ->where(fn ($q) => $q->where('vehiculo_id', $vehiculo->id)
                ->orWhere(fn ($q) => $q->whereNull('vehiculo_id')
                    ->where(fn ($q) => $q->whereNull('clase_vehiculo_id')->orWhere('clase_vehiculo_id', $vehiculo->clase_vehiculo_id))))
            ->orderBy('orden')->orderBy('id')
            ->get()
            ->sortByDesc(fn (RutinaMantenimiento $r) => $r->vehiculo_id ? 2 : ($r->clase_vehiculo_id ? 1 : 0))
            ->unique(fn (RutinaMantenimiento $r) => self::clave($r->nombre))
            ->sortBy(fn (RutinaMantenimiento $r) => [$r->orden, $r->id])
            ->values();
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
