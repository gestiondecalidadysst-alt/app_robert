<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Cambio de clave obligatorio para los usuarios del panel /app.
 *
 * El administrador de una empresa nueva entra con una clave temporal recibida por correo
 * y con `debe_cambiar_clave`. El middleware `ForzarCambioClave` lo trae aquí y no lo deja
 * usar el panel hasta que define una clave propia. Es una ruta plana (no una página del
 * panel) para no depender del enrutado por empresa de Filament.
 */
class CambiarClaveController extends Controller
{
    public function mostrar(Request $request): View|RedirectResponse
    {
        if (! $request->user()?->debe_cambiar_clave) {
            return redirect(Filament::getPanel('app')->getUrl());
        }

        return view('auth.cambiar-clave');
    }

    public function guardar(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'clave' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()->mixedCase()],
        ], [], ['clave' => 'contraseña nueva']);

        if (Hash::check($datos['clave'], $usuario->password)) {
            return back()->withErrors(['clave' => 'La nueva no puede ser igual a la temporal que recibió.']);
        }

        $usuario->forceFill([
            'password' => $datos['clave'],
            'debe_cambiar_clave' => false,
        ])->save();

        // Invalida la sesión en otros dispositivos: la temporal viajó por correo.
        Auth::logoutOtherDevices($datos['clave']);
        $request->session()->regenerate();

        return redirect(Filament::getPanel('app')->getUrl())->with('aviso', 'Contraseña actualizada.');
    }
}
