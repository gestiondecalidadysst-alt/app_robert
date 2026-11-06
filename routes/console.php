<?php

use App\Support\Sistema\Latidos;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de firma de contratos y avisos de enlaces vencidos (los corre el worker con schedule:work).
Schedule::command('ruta:firmas')->dailyAt('09:10')->withoutOverlapping()->onOneServer();

// Mantenimiento preventivo: planes de acción por rutinas vencidas por fecha.
Schedule::command('ruta:mantenimiento')->dailyAt('06:10')->withoutOverlapping()->onOneServer();

// Resumen de gerencia del mes anterior (correo con PDF y webhook), el día 1 a las 7:10 a. m.
Schedule::command('ruta:informe-gerencia')->monthlyOn(1, '07:10')->withoutOverlapping()->onOneServer();

// Latido de las tareas programadas (Estado del sistema) y vigilante de la cola.
Schedule::call(fn () => Latidos::marcar(Latidos::PROGRAMADAS, true))
    ->name('latido-programadas')->everyMinute()->onOneServer();
Schedule::command('ruta:vigilar')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Consultas automáticas de tránsito (solo empresas que las activaron), a las 5:20 a. m.
Schedule::command('ruta:consultas-transito')->dailyAt('05:20')->withoutOverlapping()->onOneServer();

// Contingencia del RNDC: reencola lo que quedó esperando si la cola se perdió.
Schedule::command('ruta:rndc-contingencia')->everyTenMinutes()->withoutOverlapping()->onOneServer();

// Cuota de administración del mes por vehículo (empresas con el módulo), el día 1 a las 6:30 a. m.
Schedule::command('ruta:generar-cuotas-administracion')->monthlyOn(1, '06:30')->withoutOverlapping()->onOneServer();

// Centro de alertas: aviso puntual de lo que vence hoy (diario) y resumen los lunes (semanal).
Schedule::command('ruta:alertas --modo=diario')->dailyAt('06:40')->withoutOverlapping()->onOneServer();
Schedule::command('ruta:alertas --modo=semanal')->weeklyOn(1, '07:00')->withoutOverlapping()->onOneServer();
