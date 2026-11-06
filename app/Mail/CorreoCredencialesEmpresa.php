<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo al administrador de una empresa recién creada con sus credenciales de acceso.
 *
 * Lleva una contraseña temporal que el sistema obliga a cambiar en el primer ingreso,
 * así que no se guarda en ningún lado: solo viaja en este correo. Es texto simple para
 * poder ir cifrada en la cola.
 */
class CorreoCredencialesEmpresa extends Mailable
{
    use Queueable;

    public function __construct(
        public string $empresa,
        public string $nombre,
        public string $correo,
        public string $claveTemporal,
        public string $enlace,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('app.name')),
            subject: 'Acceso a '.$this->empresa,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'correo.credenciales-empresa');
    }
}
