<?php

// Configuración propia de la plataforma Ruta.
return [
    // Superadministrador inicial (lo usa `php artisan ruta:superadmin --si-no-existe` al desplegar).
    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Superadministrador'),
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    // Días antes del vencimiento en que un documento pasa a "por vencer" (semáforo amarillo).
    'dias_aviso_vencimiento' => (int) env('DIAS_AVISO_VENCIMIENTO', 30),
];
