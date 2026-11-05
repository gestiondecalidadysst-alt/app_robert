<?php

namespace App\Jobs;

use App\Models\EnvioMensaje;
use App\Services\Mensajeria\Mensajero;
use App\Support\Sistema\Colas;
use App\Support\Sistema\FallasEnvio;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
    ) {
        $this->onQueue(Colas::ALTA);
    }

    public function handle(): void
    {
        $envio = EnvioMensaje::query()->withoutGlobalScopes()->find($this->envioId);

        if (! $envio || $envio->estado === 'enviado') {
            return;
        }

        if (Mensajero::enviarSmsAhora($envio, $this->celular, $this->texto)) {
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
