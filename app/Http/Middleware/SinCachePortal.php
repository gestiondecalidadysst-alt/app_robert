<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Evita que el portal del conductor (páginas con sesión y el service worker) se guarde en cachés
 * intermedias (navegador, proxy o CDN). Así una respuesta no queda "pegada" para otra ruta.
 */
class SinCachePortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('Cache-Control')
            || ! str_contains((string) $response->headers->get('Cache-Control'), 'no-store')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        return $response;
    }
}
