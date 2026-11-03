<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo al cliente con su recibo de caja o con el estado de cuenta / paz y salvo (PDF adjunto).
 * Solo lleva datos simples para poder ir cifrado en la cola.
 */
class CorreoReciboPago extends Mailable
{
    use Queueable;

    public const RECIBO = 'recibo';

    public const ESTADO_CUENTA = 'estado_cuenta';

    public function __construct(
        public string $tipo,
        public string $empresa,
        public string $destinatario,
        public string $numeroContrato,
        public string $titulo,
        public string $detalle,
        public string $pdf,
        public string $nombreArchivo,
        public ?string $enlace = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->empresa),
            subject: "{$this->titulo} · contrato No. {$this->numeroContrato}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'correo.recibo-pago');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => base64_decode($this->pdf), $this->nombreArchivo)->withMime('application/pdf'),
        ];
    }
}
