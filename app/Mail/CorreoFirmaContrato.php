<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correos del proceso de firma: enlace, recordatorio, código de verificación y contrato firmado.
 * Solo lleva datos simples (sin modelos) para poder ir cifrado en la cola.
 */
class CorreoFirmaContrato extends Mailable
{
    use Queueable;

    public const ENLACE = 'enlace';

    public const RECORDATORIO = 'recordatorio';

    public const CODIGO = 'codigo';

    public const FIRMADO = 'firmado';

    /**
     * @param  string|null  $pdf  PDF firmado en base64 (solo para FIRMADO)
     */
    public function __construct(
        public string $tipo,
        public string $empresa,
        public string $numeroContrato,
        public string $destinatario,
        public ?string $enlace = null,
        public ?string $vence = null,
        public ?string $codigo = null,
        public ?string $pdf = null,
        public ?string $nombreArchivo = null,
    ) {}

    public function envelope(): Envelope
    {
        $asunto = match ($this->tipo) {
            self::CODIGO => "Código para firmar el contrato No. {$this->numeroContrato}",
            self::FIRMADO => "Contrato No. {$this->numeroContrato} firmado",
            self::RECORDATORIO => "Recordatorio: contrato No. {$this->numeroContrato} pendiente de firma",
            default => "{$this->empresa}: contrato No. {$this->numeroContrato} para su firma",
        };

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->empresa),
            subject: $asunto,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'correo.firma-contrato');
    }

    public function attachments(): array
    {
        if ($this->tipo !== self::FIRMADO || ! $this->pdf) {
            return [];
        }

        return [
            Attachment::fromData(fn () => base64_decode($this->pdf), $this->nombreArchivo ?? 'contrato-firmado.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
