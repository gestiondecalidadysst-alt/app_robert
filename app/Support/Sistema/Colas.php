<?php

namespace App\Support\Sistema;

/**
 * Filas de la cola. El worker atiende primero la alta:
 *   php artisan queue:work --queue=alta,default,baja
 * - alta: SMS y correos (recordatorios de firma, resumen de gerencia…).
 * - baja: webhooks a n8n (pueden acumularse sin retrasar los mensajes).
 */
final class Colas
{
    public const ALTA = 'alta';

    public const BAJA = 'baja';

    /** Orden en que las atiende el worker. */
    public const ORDEN = [self::ALTA, 'default', self::BAJA];
}
