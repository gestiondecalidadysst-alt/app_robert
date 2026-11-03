<?php

namespace App\Actions\Mantenimiento;

use App\Models\ActividadMantenimiento;
use App\Models\Empresa;
use App\Support\Avisos;
use App\Support\Webhooks;
use Filament\Notifications\Notification;

/**
 * Avisos diarios del programa de mantenimiento (una sola vez por actividad y tipo):
 * - Próxima: faltan los días configurados por la empresa (por defecto 7).
 * - Vencida: pasó la fecha programada sin ejecutarse.
 * Van por webhook (una petición por actividad) y como resumen en la campana.
 */
final class AvisosPrograma
{
    /** @return array{proximas: int, vencidas: int} */
    public static function enviar(Empresa $empresa): array
    {
        $programa = ProgramaAnual::programaVigente($empresa->id, (int) today()->year);
        if (! $programa) {
            return ['proximas' => 0, 'vencidas' => 0];
        }

        $dias = max(1, (int) ($empresa->mantenimiento_dias_aviso ?: 7));
        $base = fn () => $programa->actividades()->pendientes()->with('vehiculo');

        $proximas = $base()->whereNull('avisado_proximo_at')
            ->whereDate('fecha_programada', '>=', today())->whereDate('fecha_programada', '<=', today()->addDays($dias))->get();
        $vencidas = $base()->whereNull('avisado_vencido_at')->whereDate('fecha_programada', '<', today())->get();

        foreach ($proximas as $a) {
            Webhooks::emitir($empresa, 'mantenimiento.actividad_proxima', self::datos($a));
            $a->forceFill(['avisado_proximo_at' => now()])->save();
        }
        foreach ($vencidas as $a) {
            Webhooks::emitir($empresa, 'mantenimiento.actividad_vencida', self::datos($a));
            $a->forceFill(['avisado_vencido_at' => now()])->save();
        }

        if ($proximas->isNotEmpty() || $vencidas->isNotEmpty()) {
            Avisos::aQuienPueda($empresa, 'ver_mantenimientos', Notification::make()
                ->warning()
                ->title('Programa de mantenimiento')
                ->body(collect([
                    $proximas->isNotEmpty() ? "{$proximas->count()} actividad(es) en los próximos {$dias} días" : null,
                    $vencidas->isNotEmpty() ? "{$vencidas->count()} vencida(s) sin ejecutar" : null,
                ])->filter()->implode(' · ') . '.'));
        }

        return ['proximas' => $proximas->count(), 'vencidas' => $vencidas->count()];
    }

    public static function datos(ActividadMantenimiento $a): array
    {
        return [
            'actividad_id' => $a->id,
            'placa' => $a->vehiculo?->placa,
            'numero_interno' => $a->vehiculo?->numero_interno,
            'rutina' => $a->rutina_nombre,
            'frecuencia' => $a->frecuencia,
            'fecha_programada' => $a->fecha_programada->toDateString(),
            'km_programado' => $a->km_programado,
            'dias' => (int) today()->diffInDays($a->fecha_programada, false),
            'reprogramada' => $a->reprogramada(),
            'responsable' => $a->vehiculo?->propietario?->nombre_completo,
            'telefono_responsable' => $a->vehiculo?->propietario?->telefono,
        ];
    }
}
