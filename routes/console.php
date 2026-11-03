<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de firma de contratos y avisos de enlaces vencidos (los corre el worker con schedule:work).
\Illuminate\Support\Facades\Schedule::command('ruta:firmas')->dailyAt('09:10')->withoutOverlapping();

// Mantenimiento preventivo: planes de acción por rutinas vencidas por fecha.
\Illuminate\Support\Facades\Schedule::command('ruta:mantenimiento')->dailyAt('06:10')->withoutOverlapping();

// Resumen de gerencia del mes anterior (correo con PDF y webhook), el día 1 a las 7:10 a. m.
\Illuminate\Support\Facades\Schedule::command('ruta:informe-gerencia')->monthlyOn(1, '07:10')->withoutOverlapping();
