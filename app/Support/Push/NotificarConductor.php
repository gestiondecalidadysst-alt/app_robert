<?php

namespace App\Support\Push;

use App\Enums\EstadoVigencia;
use App\Enums\ModuloEmpresa;
use App\Models\ActividadMantenimiento;
use App\Models\AsignacionVehiculo;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\Fuec;
use App\Support\Modulos;

/**
 * Notificaciones push al conductor sobre lo suyo: un nuevo extracto (servicio) asignado, el
 * mantenimiento de su vehículo y el preoperacional pendiente del día. Nunca rompe la operación:
 * si el envío falla o no hay llaves VAPID, simplemente no pasa nada.
 */
final class NotificarConductor
{
    /** Envía a los dispositivos del conductor (si tiene usuario del portal). */
    public static function aConductor(?Conductor $conductor, string $titulo, string $cuerpo, ?string $tag = null): int
    {
        if (! $conductor?->usuario) {
            return 0;
        }

        return (int) rescue(
            fn () => WebPush::aUsuario($conductor->usuario, $titulo, $cuerpo, route('conductor.inicio'), $tag),
            0,
            report: false,
        );
    }

    /** Nuevo extracto (FUEC) asignado: avisa a cada conductor del servicio. */
    public static function nuevoExtracto(Fuec $fuec): void
    {
        $fuec->loadMissing(['conductores', 'origen', 'destino']);

        $ruta = trim(($fuec->origen?->nombre ?? '').' → '.($fuec->destino?->nombre ?? ''), " \u{2192}");
        $cuerpo = trim(($ruta !== '' ? $ruta.' · ' : '')
            .'desde el '.$fuec->fecha_inicio->format('d/m/Y').'. Extracto '.$fuec->numero_formateado);

        foreach ($fuec->conductores as $conductor) {
            self::aConductor($conductor, 'Nuevo servicio asignado', $cuerpo, 'extracto-'.$fuec->id);
        }
    }

    /** Mantenimiento del vehículo: avisa al conductor que lo tiene asignado. */
    public static function mantenimiento(ActividadMantenimiento $actividad, bool $vencida): void
    {
        $conductor = self::conductorDeVehiculo((int) $actividad->vehiculo_id);
        if (! $conductor) {
            return;
        }

        $placa = $actividad->vehiculo?->placa ?? 'tu vehículo';
        $fecha = $actividad->fecha_programada->format('d/m/Y');

        $vencida
            ? self::aConductor($conductor, 'Mantenimiento vencido',
                "El vehículo {$placa} tiene vencido el mantenimiento \"{$actividad->rutina_nombre}\" (programado {$fecha}). Avisa a la empresa.",
                'mantto-venc-'.$actividad->id)
            : self::aConductor($conductor, 'Mantenimiento próximo',
                "El vehículo {$placa} tiene mantenimiento \"{$actividad->rutina_nombre}\" el {$fecha}.",
                'mantto-prox-'.$actividad->id);
    }

    /**
     * Preoperacional pendiente: a los conductores con un servicio que empieza hoy y cuyo vehículo
     * aún no tiene el preoperacional del día. Devuelve cuántos avisos se enviaron.
     */
    public static function preoperacionalPendiente(Empresa $empresa): int
    {
        if (! Modulos::activo($empresa, ModuloEmpresa::SeguridadVial)) {
            return 0;
        }

        $fuecs = Fuec::query()->deEmpresa($empresa)
            ->whereNull('anulado_at')
            ->whereDate('fecha_inicio', today())
            ->with(['conductores', 'vehiculo'])
            ->get();

        $enviados = 0;
        foreach ($fuecs as $fuec) {
            if ($fuec->estado() !== EstadoVigencia::Vigente) {
                continue;
            }
            if ($fuec->vehiculo?->preoperacionalDelDia()) {
                continue; // ya lo hizo
            }
            foreach ($fuec->conductores as $conductor) {
                $enviados += self::aConductor($conductor, 'Preoperacional pendiente',
                    "Tienes servicio hoy con {$fuec->vehiculo?->placa}. Haz el preoperacional antes de salir.",
                    'preop-'.$fuec->id);
            }
        }

        return $enviados;
    }

    /** Conductor vigente del vehículo (el principal si hay varios). */
    private static function conductorDeVehiculo(int $vehiculoId): ?Conductor
    {
        return AsignacionVehiculo::query()->withoutGlobalScopes()
            ->where('vehiculo_id', $vehiculoId)
            ->whereNull('fecha_hasta')
            ->orderByDesc('principal')
            ->with('conductor')
            ->first()?->conductor;
    }
}
