<?php

namespace App\Support\Pesv;

use App\Enums\EstadoConductor;
use App\Enums\EstadoVigencia;
use App\Models\AsignacionVehiculo;
use App\Models\Conductor;
use App\Models\Fuec;
use App\Models\Inspeccion;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lo que ve el conductor en el portal: sus servicios (FUEC vigentes y programados en los que va
 * como conductor) y los vehículos que tiene asignados, con el estado del preoperacional del día.
 *
 * Regla acordada con el usuario: el extracto solo se puede ver el día del servicio y después de un
 * preoperacional APTO del vehículo ese día.
 */
final class ServiciosConductor
{
    /** Hojas de vida del usuario que pueden usar el portal (no retiradas), en cualquier empresa. */
    public static function conductores(User $usuario): Collection
    {
        return Conductor::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('user_id', $usuario->id)
            ->where('estado', '!=', EstadoConductor::Retirado->value)
            // Acceso desactivado en Configuración → Usuarios de esa empresa.
            ->whereNotIn('empresa_id', DB::table('empresa_user')->where('user_id', $usuario->id)->where('activo', false)->select('empresa_id'))
            ->with('empresa')
            ->get();
    }

    /** @return Collection<int, Fuec> FUEC vigentes o programados del conductor, del más próximo al más lejano. */
    public static function fuecs(User $usuario): Collection
    {
        $ids = self::conductores($usuario)->pluck('id');

        return Fuec::query()->withoutGlobalScopes()
            ->whereNull('anulado_at')
            ->whereDate('fecha_fin', '>=', today())
            ->whereHas('conductores', fn ($q) => $q->whereIn('conductores.id', $ids))
            ->with(['vehiculo', 'origen', 'destino', 'contrato.cliente', 'empresa'])
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->limit(30)
            ->get();
    }

    /**
     * Tarjeta de un FUEC con lo que el conductor puede hacer hoy.
     *
     * @return array{fuec: Fuec, estado: EstadoVigencia, preoperacional: ?Inspeccion, puede_preoperacional: bool, puede_extracto: bool, motivo_extracto: ?string}
     */
    public static function tarjeta(Fuec $fuec): array
    {
        $estado = $fuec->estado();
        $vigente = $estado === EstadoVigencia::Vigente;
        $preop = $vigente ? $fuec->vehiculo?->preoperacionalDelDia() : null;

        $motivo = match (true) {
            ! $vigente => 'El extracto se habilita el día del servicio, después del preoperacional.',
            ! $preop => 'Haga el preoperacional de hoy para ver el extracto.',
            ! $preop->apta() => 'El vehículo quedó NO APTO en el preoperacional de hoy. Avise a la empresa.',
            default => null,
        };

        return [
            'fuec' => $fuec,
            'estado' => $estado,
            'preoperacional' => $preop,
            'puede_preoperacional' => $vigente && ! $preop?->apta(),
            'puede_extracto' => $motivo === null,
            'motivo_extracto' => $motivo,
        ];
    }

    /** ¿El usuario va como conductor en este FUEC? */
    public static function esSuyo(User $usuario, Fuec $fuec): bool
    {
        return $fuec->conductores()->whereIn('conductores.id', self::conductores($usuario)->pluck('id'))->exists();
    }

    /** Conductor del usuario que va en el FUEC (o el de la empresa del vehículo, para el preoperacional sin servicio). */
    public static function conductorPara(User $usuario, int $empresaId): ?Conductor
    {
        return self::conductores($usuario)->firstWhere('empresa_id', $empresaId);
    }

    /**
     * Vehículos asignados al conductor (para hacer el preoperacional aunque no tenga servicio hoy).
     *
     * @return Collection<int, array{vehiculo: Vehiculo, preoperacional: ?Inspeccion}>
     */
    public static function vehiculosAsignados(User $usuario): Collection
    {
        $ids = self::conductores($usuario)->pluck('id');

        return AsignacionVehiculo::query()->withoutGlobalScopes()
            ->whereIn('conductor_id', $ids)
            ->whereNull('fecha_hasta')
            ->with('vehiculo')
            ->get()
            ->pluck('vehiculo')
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn (Vehiculo $v) => ['vehiculo' => $v, 'preoperacional' => $v->preoperacionalDelDia()]);
    }

    /** ¿Puede el usuario hacer el preoperacional de este vehículo? (asignado o en un FUEC suyo vigente hoy) */
    public static function puedeInspeccionar(User $usuario, Vehiculo $vehiculo): bool
    {
        if (self::vehiculosAsignados($usuario)->contains(fn (array $a) => $a['vehiculo']->id === $vehiculo->id)) {
            return true;
        }

        return self::fuecs($usuario)->contains(fn (Fuec $f) => $f->vehiculo_id === $vehiculo->id && $f->estado() === EstadoVigencia::Vigente);
    }
}
