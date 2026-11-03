<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Resumen mensual de gerencia (PDF adjunto). Solo datos simples para la cola. */
class CorreoInformeGerencia extends Mailable
{
    use Queueable;

    public function __construct(
        public string $empresa,
        public string $destinatario,
        public string $periodo,
        public string $pdf,
        public string $nombreArchivo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->empresa),
            subject: "Resumen de gerencia · {$this->periodo}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'correo.informe-gerencia');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => base64_decode($this->pdf), $this->nombreArchivo)->withMime('application/pdf')];
    }
}
