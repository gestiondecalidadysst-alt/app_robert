<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Prueba el envío de correo con la configuración actual (SMTP de Brevo u otro).
 *
 *   php artisan ruta:probar-correo tu@correo.com
 *
 * Envía de forma síncrona para que cualquier error del servidor SMTP aparezca aquí mismo,
 * sin pasar por la cola. No guarda nada: solo manda un correo de prueba.
 */
class ProbarCorreo extends Command
{
    protected $signature = 'ruta:probar-correo {destino : Correo al que enviar la prueba}';

    protected $description = 'Envía un correo de prueba para verificar la configuración de envío';

    public function handle(): int
    {
        $destino = (string) $this->argument('destino');

        if (! filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            $this->error("«{$destino}» no es un correo válido.");

            return self::FAILURE;
        }

        // Se muestra la configuración (sin la clave) para detectar a simple vista un dato mal puesto.
        $this->line('Enviando con esta configuración:');
        $this->table(['Dato', 'Valor'], [
            ['Mailer', config('mail.default')],
            ['Host', config('mail.mailers.smtp.host')],
            ['Puerto', config('mail.mailers.smtp.port')],
            ['Esquema', config('mail.mailers.smtp.scheme') ?: '(por defecto)'],
            ['Usuario', config('mail.mailers.smtp.username') ?: '(vacío)'],
            ['Clave', config('mail.mailers.smtp.password') ? '(definida)' : '(VACÍA)'],
            ['Remitente', config('mail.from.address').' · '.config('mail.from.name')],
        ]);

        if (config('mail.default') === 'log') {
            $this->warn('MAIL_MAILER está en «log»: el correo se escribe en storage/logs, no se envía de verdad.');
        }

        if (blank(config('mail.mailers.smtp.password')) && config('mail.default') === 'smtp') {
            $this->error('La clave SMTP (MAIL_PASSWORD) está vacía. Defínala en Easypanel y vuelva a desplegar.');

            return self::FAILURE;
        }

        try {
            Mail::raw(
                "Este es un correo de prueba de GeoTrack.\n\nSi lo recibió, la configuración de envío quedó funcionando.\n\nEnviado el ".now()->format('d/m/Y h:i a'),
                fn ($mensaje) => $mensaje->to($destino)->subject('Prueba de correo · GeoTrack'),
            );
        } catch (Throwable $e) {
            $this->error('No se pudo enviar: '.$e->getMessage());
            $this->newLine();
            $this->line('Causas típicas:');
            $this->line(' · La clave SMTP (MAIL_PASSWORD) es incorrecta o está vencida.');
            $this->line(' · El remitente ('.config('mail.from.address').') no está verificado en Brevo.');
            $this->line(' · El host o el puerto están mal (debe ser smtp-relay.brevo.com y 587).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Correo de prueba enviado a {$destino}.");
        $this->line('Revise la bandeja de entrada (y la carpeta de spam). En Brevo, el envío aparece en Transactional → Logs.');

        return self::SUCCESS;
    }
}
