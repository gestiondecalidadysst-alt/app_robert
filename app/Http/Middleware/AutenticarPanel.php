<?php

namespace App\Http\Middleware;

use App\Support\Pesv\ServiciosConductor;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Autenticación de los paneles /admin y /app.
 *
 * Caso especial: si en el navegador quedó abierta la sesión de un conductor (portal /conductor),
 * entrar al panel no muestra "403 Forbidden": se cierra esa sesión y se lleva al inicio de sesión
 * del panel con un aviso. Los demás usuarios sin acceso siguen recibiendo 403.
 */
class AutenticarPanel extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();
        $usuario = $guard->user();

        if ($usuario instanceof FilamentUser
            && ! $usuario->canAccessPanel(Filament::getCurrentOrDefaultPanel())
            && ServiciosConductor::conductores($usuario)->isNotEmpty()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Notification::make()
                ->warning()
                ->title('Se cerró la sesión del portal del conductor')
                ->body('En este navegador estaba abierta la sesión de un conductor. Ingrese con su usuario de la empresa.')
                ->persistent()
                ->send();

            throw new HttpResponseException(redirect()->to(Filament::getLoginUrl()));
        }

        parent::authenticate($request, $guards);
    }
}
