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

        $panel = Filament::getCurrentOrDefaultPanel();

        if ($usuario instanceof FilamentUser
            && ! $usuario->canAccessPanel($panel)
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

        // Un usuario de empresa que llega al panel /admin no debe ver un 403 crudo ni el
        // formulario de acceso: el panel de administración es solo del superadministrador.
        // Se le envía a su propio panel, donde sí tiene acceso.
        if ($usuario instanceof FilamentUser
            && $panel->getId() === 'admin'
            && ! $usuario->es_superadmin
            && $usuario->canAccessPanel(Filament::getPanel('app'))) {
            throw new HttpResponseException(redirect()->to(Filament::getPanel('app')->getUrl()));
        }

        parent::authenticate($request, $guards);
    }
}
