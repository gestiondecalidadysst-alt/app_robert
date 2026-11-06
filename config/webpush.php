<?php

// Notificaciones Web Push (PWA del conductor). Las llaves VAPID se generan una vez y se ponen
// en el entorno (Easypanel). La privada nunca sale del servidor; la pública va al navegador.
return [
    'subject' => env('VAPID_SUBJECT', 'mailto:servicios@solucionesapp.pro'),
    'public_key' => env('VAPID_PUBLIC'),
    // PEM de la clave privada EC (prime256v1) en base64, en una sola línea.
    'private_pem' => env('VAPID_PRIVATE_PEM_B64'),
];
