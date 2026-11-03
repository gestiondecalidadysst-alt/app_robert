<?php

namespace App\Jobs;

use App\Models\EnvioMensaje;
use App\Services\Mensajeria\Mensajero;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Envía un SMS en segundo plano con reintentos. Cifrado en la cola: lleva el número y el texto
 * (que puede incluir el enlace de firma).
 */
class EnviarSms implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(
        public int $envioId,
        public string $celular,
        public string $texto,
    )
    {
        $this->onQueue(\App\Support\Sistema\Colas::ALTA);
    }

    public function handle(): void
    {
        $envio = EnvioMensaje::query()->withoutGlobalScopes()->find($this->envioId);

        if (! $envio || $envio->estado === 'enviado') {
            return;
        }

        if (! Mensajero::enviarSmsAhora($envio, $this->celular, $this->texto) && $this->attempts() < $this->tries) {
            throw new RuntimeException($envio->error ?? 'No se pudo enviar el SMS.'); // reintento
        }
    }
}
