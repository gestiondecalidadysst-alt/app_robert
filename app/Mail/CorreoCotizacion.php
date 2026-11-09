<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo al cliente con la cotización en PDF adjunta. Lleva datos simples + el PDF ya generado,
 * para ir cifrado en la cola.
 */
class CorreoCotizacion extends Mailable
{
    use Queueable;

    public function __construct(
        public string $empresa,
        public string $destinatario,
        public string $numero,
        public ?string $asunto,
        public string $pdf,
        public string $nombrePdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->empresa),
            subject: "Cotización {$this->numero} · {$this->empresa}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'correo.cotizacion');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->nombrePdf)->withMime('application/pdf'),
        ];
    }
}
