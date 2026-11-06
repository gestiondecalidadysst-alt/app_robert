<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras el usuario tenga `debe_cambiar_clave`, el panel /app lo retiene en la página
 * de cambio de clave. Así, el administrador que entra por primera vez con la clave genérica
 * que recibió por correo no puede usar nada hasta definir una propia.
 *
 * Se dejan pasar la propia ruta de cambio, el cierre de sesión y las peticiones de Livewire
 * (para que la página siga funcionando). La redirección es a una ruta plana, fuera del panel.
 */
class ForzarCambioClave
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario?->debe_cambiar_clave
            && ! $request->routeIs('clave.obligatoria', 'clave.obligatoria.guardar')
            && ! $request->routeIs('filament.app.auth.logout')
            && ! $request->is('livewire/*')) {
            return redirect()->route('clave.obligatoria');
        }

        return $next($request);
    }
}
