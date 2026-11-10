<?php

namespace App\Jobs;

use App\Support\Sistema\Colas;
use App\Support\Webhooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Envía una alerta de plataforma (error inesperado o servicio caído) al webhook configurado en
 * ALERTAS_WEBHOOK_URL, firmada igual que los webhooks por empresa.
 */
class EnviarAvisoPlataforma implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly string $evento,
        private readonly array $payload,
    ) {
        $this->onQueue(Colas::BAJA);
    }

    public function handle(): void
    {
        $url = config('ruta.alertas_webhook_url');

        if (blank($url)) {
            return;
        }

        $cuerpo = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Http::timeout(15)
            ->withHeaders(array_filter([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Ruta-Webhooks/1.0',
                'X-Ruta-Evento' => $this->evento,
                'X-Ruta-Entrega' => $this->payload['id'] ?? null,
                'X-Ruta-Firma' => Webhooks::firmar($cuerpo, config('ruta.alertas_webhook_secreto')),
            ]))
            ->withBody($cuerpo, 'application/json')
            ->post($url)
            ->throw();
    }
}
