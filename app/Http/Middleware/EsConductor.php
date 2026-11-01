<?php

namespace App\Http\Middleware;

use App\Support\Pesv\ServiciosConductor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal del conductor: exige sesión de un usuario activo con al menos una hoja de vida de
 * conductor no retirada. Si tiene clave temporal, lo lleva a cambiarla antes de seguir.
 */
class EsConductor
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            return redirect()->guest(route('conductor.ingresar'));
        }

        if (! $usuario->activo || ServiciosConductor::conductores($usuario)->isEmpty()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('conductor.ingresar')->withErrors(['documento' => 'Este usuario no tiene acceso al portal del conductor.']);
        }

        if ($usuario->debe_cambiar_clave && ! $request->routeIs('conductor.clave*', 'conductor.salir')) {
            return redirect()->route('conductor.clave');
        }

        $respuesta = $next($request);
        $respuesta->headers->set('Cache-Control', 'no-store, private');
        $respuesta->headers->set('Referrer-Policy', 'no-referrer');

        return $respuesta;
    }
}
