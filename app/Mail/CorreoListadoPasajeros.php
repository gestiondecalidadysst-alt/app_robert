<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo al cliente del contrato con el enlace para que los pasajeros se autorregistren
 * en el listado del FUEC. Lleva datos simples para ir cifrado en la cola.
 */
class CorreoListadoPasajeros extends Mailable
{
    use Queueable;

    public function __construct(
        public string $empresa,
        public string $destinatario,
        public string $numeroFuec,
        public string $enlace,
        public ?int $cupos = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->empresa),
            subject: "Registro de pasajeros · FUEC {$this->numeroFuec} · {$this->empresa}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'correo.listado-pasajeros');
    }
}
