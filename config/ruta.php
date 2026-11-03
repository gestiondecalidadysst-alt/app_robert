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

    // Correo que recibe las alertas de errores inesperados (además de la campana de los superadministradores).
    'alertas_correo' => env('ALERTAS_CORREO'),

    // Onurix (SMS). Las credenciales son de cada empresa (Configuración → Integraciones).
    'onurix' => [
        'url' => env('ONURIX_URL', 'https://www.onurix.com/api/v1'),
    ],
];
