<?php

namespace App\Jobs;

use App\Models\Empresa;
use App\Models\WebhookEntrega;
use App\Support\Webhooks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Envía un aviso por webhook (POST JSON firmado) con reintentos. */
class EnviarWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public int $entregaId) {}

    public function handle(): void
    {
        $entrega = WebhookEntrega::query()->withoutGlobalScopes()->find($this->entregaId);
        if (! $entrega || $entrega->estado === 'enviado') {
            return;
        }

        $empresa = Empresa::find($entrega->empresa_id);
        if (! $empresa?->webhookConfigurado()) {
            $entrega->forceFill(['estado' => 'fallido', 'error' => 'El webhook está desactivado.'])->save();

            return;
        }

        $cuerpo = json_encode($entrega->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $entrega->increment('intentos');

        try {
            $respuesta = Http::timeout(15)
                ->withHeaders(array_filter([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Ruta-Webhooks/1.0',
                    'X-Ruta-Evento' => $entrega->evento,
                    'X-Ruta-Entrega' => (string) $entrega->id,
                    'X-Ruta-Firma' => Webhooks::firmar($cuerpo, $empresa->webhook_secreto),
                ]))
                ->withBody($cuerpo, 'application/json')
                ->post($empresa->webhook_url);

            $entrega->forceFill([
                'codigo_respuesta' => $respuesta->status(),
                'estado' => $respuesta->successful() ? 'enviado' : 'fallido',
                'error' => $respuesta->successful() ? null : mb_substr('HTTP ' . $respuesta->status() . ': ' . $respuesta->body(), 0, 500),
                'enviado_at' => $respuesta->successful() ? now() : null,
            ])->save();

            if (! $respuesta->successful()) {
                throw new RuntimeException("El webhook respondió {$respuesta->status()}.");
            }
        } catch (Throwable $e) {
            if ($entrega->estado !== 'fallido') {
                $entrega->forceFill(['estado' => 'fallido', 'error' => mb_substr($e->getMessage(), 0, 500)])->save();
            }

            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }
}
