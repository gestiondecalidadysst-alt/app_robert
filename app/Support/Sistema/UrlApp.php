<?php

namespace App\Support\Sistema;

/**
 * APP_URL es la dirección pública de la aplicación. Se usa en todo lo que se arma fuera de una
 * petición del navegador: enlaces de SMS y correos enviados por la cola, QR de PDFs generados por
 * tareas programadas, archivos públicos. Si quedó en localhost, esos enlaces no funcionan.
 */
final class UrlApp
{
    public static function sinConfigurar(): bool
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host === '' || $host === 'localhost' || str_starts_with($host, '127.') || $host === '0.0.0.0';
    }
}
