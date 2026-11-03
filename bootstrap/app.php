<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás del proxy de Easypanel (Traefik): respeta HTTPS y la IP real del visitante.
        $middleware->trustProxies(at: '*');
        $middleware->alias(['conductor' => \App\Http\Middleware\EsConductor::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Errores inesperados: código de referencia en el log y en la página de error, registro
        // agrupado y alerta a los superadministradores (App\Support\Sistema\RegistroErrores).
        // Las validaciones, 403, 404 y sesión vencida no se reportan (no son fallas del sistema).
        $exceptions->context(fn () => ['referencia' => \App\Support\Sistema\Referencia::actual()]);
        $exceptions->reportable(function (\Throwable $e): void {
            \App\Support\Sistema\RegistroErrores::registrar($e);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
