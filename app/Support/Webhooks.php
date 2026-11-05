<?php

namespace App\Support;

use App\Jobs\EnviarWebhook;
use App\Models\Empresa;
use App\Models\WebhookEntrega;
use Illuminate\Support\Str;
use Throwable;

/**
 * Avisos por webhook (n8n u otro). Cada empresa configura la URL, el secreto y los eventos en
 * Configuración → Integraciones. El envío va en cola con reintentos y queda en webhook_entregas.
 *
 * Cada petición es un POST JSON con las cabeceras:
 * - X-Ruta-Evento: nombre del evento.
 * - X-Ruta-Entrega: id de la entrega (para no procesar dos veces un reintento).
 * - X-Ruta-Firma: sha256=<HMAC-SHA256 del cuerpo con el secreto> (si hay secreto).
 */
final class Webhooks
{
    public const EVENTOS = [
        'mantenimiento.actividad_proxima' => 'Actividad del programa de mantenimiento próxima',
        'mantenimiento.actividad_vencida' => 'Actividad del programa de mantenimiento vencida',
        'mantenimiento.registrado' => 'Mantenimiento registrado',
        'mantenimiento.programa_aprobado' => 'Programa anual de mantenimiento aprobado',
        'plan_accion.creado' => 'Plan de acción abierto',
        'plan_accion.por_verificar' => 'Plan de acción con evidencia (por verificar)',
        'plan_accion.cerrado' => 'Plan de acción cerrado',
        'preoperacional.no_apto' => 'Preoperacional NO APTO',
        'servicio.novedad' => 'Novedad reportada por el conductor en un servicio',
        'gerencia.informe_mensual' => 'Resumen mensual de gerencia (día 1 de cada mes)',
        'transito.comparendo_nuevo' => 'Comparendo nuevo de un conductor o vehículo (consultas de tránsito)',
        'transito.licencia_suspendida' => 'Licencia de conducción suspendida según el RUNT',
    ];

    /** Encola el aviso si la empresa tiene el webhook activo y el evento elegido. Nunca interrumpe la operación. */
    public static function emitir(Empresa|int $empresa, string $evento, array $datos): ?WebhookEntrega
    {
        try {
            $empresa = $empresa instanceof Empresa ? $empresa : Empresa::find($empresa);
            if (! $empresa?->webhookConfigurado()) {
                return null;
            }

            $eventos = $empresa->webhook_eventos ?? array_keys(self::EVENTOS);
            if ($evento !== 'prueba' && ! in_array($evento, $eventos, true)) {
                return null;
            }

            $entrega = new WebhookEntrega([
                'evento' => $evento,
                'payload' => [
                    'id' => (string) Str::uuid(),
                    'evento' => $evento,
                    'descripcion' => self::EVENTOS[$evento] ?? 'Prueba de conexión',
                    'fecha' => now()->toIso8601String(),
                    'empresa' => ['id' => $empresa->id, 'nombre' => $empresa->nombre_comercial ?: $empresa->razon_social, 'nit' => $empresa->nit],
                    'datos' => $datos,
                ],
                'estado' => 'pendiente',
            ]);
            $entrega->empresa_id = $empresa->id;
            $entrega->save();
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        try {
            EnviarWebhook::dispatch($entrega->id);
        } catch (Throwable $e) {
            // Con la cola síncrona el error del envío llega aquí; ya quedó registrado en la entrega.
            report($e);
        }

        return $entrega;
    }

    public static function firmar(string $cuerpo, ?string $secreto): ?string
    {
        return filled($secreto) ? 'sha256='.hash_hmac('sha256', $cuerpo, $secreto) : null;
    }
}
