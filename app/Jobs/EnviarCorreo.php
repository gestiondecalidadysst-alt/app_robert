<?php

namespace App\Jobs;

use App\Models\EnvioMensaje;
use App\Services\Mensajeria\Mensajero;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use RuntimeException;

/**
 * Envía un correo en segundo plano con reintentos y deja el resultado en el historial.
 */
class EnviarCorreo implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(
        public int $envioId,
        public string $correo,
        public Mailable $mensaje,
    ) {}

    public function handle(): void
    {
        $envio = EnvioMensaje::query()->withoutGlobalScopes()->find($this->envioId);

        if (! $envio || $envio->estado === 'enviado') {
            return;
        }

        if (! Mensajero::enviarCorreoAhora($envio, $this->correo, $this->mensaje) && $this->attempts() < $this->tries) {
            throw new RuntimeException($envio->error ?? 'No se pudo enviar el correo.');
        }
    }
}
