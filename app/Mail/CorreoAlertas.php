<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo de alertas al propietario o al conductor: documentos por vencer o vencidos de su vehículo
 * (o suyos) y administración en mora. Solo lleva datos simples para ir cifrado en la cola.
 *
 * @param  list<array{texto:string, detalle:string, urgente:bool}>  $items
 */
class CorreoAlertas extends Mailable
{
    use Queueable;

    public function __construct(
        public string $empresa,
        public string $destinatario,
        public string $titulo,
        public string $intro,
        public array $items,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->empresa),
            subject: $this->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'correo.alertas');
    }
}
