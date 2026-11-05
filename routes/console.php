<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de firma de contratos y avisos de enlaces vencidos (los corre el worker con schedule:work).
\Illuminate\Support\Facades\Schedule::command('ruta:firmas')->dailyAt('09:10')->withoutOverlapping()->onOneServer();

// Mantenimiento preventivo: planes de acción por rutinas vencidas por fecha.
\Illuminate\Support\Facades\Schedule::command('ruta:mantenimiento')->dailyAt('06:10')->withoutOverlapping()->onOneServer();

// Resumen de gerencia del mes anterior (correo con PDF y webhook), el día 1 a las 7:10 a. m.
\Illuminate\Support\Facades\Schedule::command('ruta:informe-gerencia')->monthlyOn(1, '07:10')->withoutOverlapping()->onOneServer();

// Latido de las tareas programadas (Estado del sistema) y vigilante de la cola.
\Illuminate\Support\Facades\Schedule::call(fn () => \App\Support\Sistema\Latidos::marcar(\App\Support\Sistema\Latidos::PROGRAMADAS, true))
    ->name('latido-programadas')->everyMinute()->onOneServer();
\Illuminate\Support\Facades\Schedule::command('ruta:vigilar')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Consultas automáticas de tránsito (solo empresas que las activaron), a las 5:20 a. m.
\Illuminate\Support\Facades\Schedule::command('ruta:consultas-transito')->dailyAt('05:20')->withoutOverlapping()->onOneServer();


// Contingencia del RNDC: reencola lo que quedó esperando si la cola se perdió.
\Illuminate\Support\Facades\Schedule::command('ruta:rndc-contingencia')->everyTenMinutes()->withoutOverlapping()->onOneServer();
