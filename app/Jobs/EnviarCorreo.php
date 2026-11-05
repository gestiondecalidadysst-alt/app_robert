<?php

namespace App\Jobs;

use App\Models\EnvioMensaje;
use App\Services\Mensajeria\Mensajero;
use App\Support\Sistema\Colas;
use App\Support\Sistema\FallasEnvio;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;

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
    ) {
        $this->onQueue(Colas::ALTA);
    }

    public function handle(): void
    {
        $envio = EnvioMensaje::query()->withoutGlobalScopes()->find($this->envioId);

        if (! $envio || $envio->estado === 'enviado') {
            return;
        }

        if (Mensajero::enviarCorreoAhora($envio, $this->correo, $this->mensaje)) {
            return;
        }

        $envio->refresh();

        // Sin créditos, IP no autorizada, credenciales o destino inválido: no se arregla reintentando y no
        // es un error del sistema. Queda «fallido» en el historial (Mensajero ya avisó a la empresa).
        if (FallasEnvio::permanente($envio->error)) {
            return;
        }

        // Falla pasajera (red, proveedor sin respuesta): se reintenta más tarde sin registrarla como error.
        if ($this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 120);
        }
    }
}
