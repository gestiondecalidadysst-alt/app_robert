<?php

namespace App\Http\Controllers\Conductor;

use App\Http\Controllers\Controller;
use App\Models\Conductor;
use App\Models\User;
use App\Enums\EstadoConductor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Ingreso al portal del conductor con su número de documento y clave, y cambio de clave.
 */
class AccesoController extends Controller
{
    public function formulario(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('conductor.inicio');
        }

        return view('conductor.ingresar');
    }

    public function ingresar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'documento' => ['required', 'string', 'max:20'],
            'clave' => ['required', 'string', 'max:100'],
        ], [], ['documento' => 'número de documento', 'clave' => 'clave']);

        $documento = preg_replace('/[^0-9A-Za-z]/', '', $datos['documento']);

        $usuario = User::query()
            ->where('activo', true)
            ->whereIn('id', Conductor::query()->withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('documento', $documento)
                ->where('estado', '!=', EstadoConductor::Retirado->value)
                ->whereNotNull('user_id')
                ->select('user_id'))
            ->get()
            ->first(fn (User $u) => Hash::check($datos['clave'], $u->password));

        if (! $usuario) {
            return back()->withInput($request->only('documento'))
                ->withErrors(['documento' => 'El documento o la clave no son correctos.']);
        }

        Auth::login($usuario, remember: true);
        $request->session()->regenerate();
        $usuario->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();

        return redirect()->intended(route('conductor.inicio'));
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('conductor.ingresar');
    }

    public function formularioClave(Request $request): View
    {
        return view('conductor.clave', ['obligatorio' => (bool) $request->user()->debe_cambiar_clave]);
    }

    public function cambiarClave(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'actual' => ['required', 'string'],
            'clave' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['actual' => 'clave actual', 'clave' => 'clave nueva']);

        if (! Hash::check($datos['actual'], $usuario->password)) {
            return back()->withErrors(['actual' => 'La clave actual no es correcta.']);
        }
        if (Hash::check($datos['clave'], $usuario->password)) {
            return back()->withErrors(['clave' => 'La clave nueva debe ser distinta de la actual.']);
        }

        $usuario->forceFill(['password' => $datos['clave'], 'debe_cambiar_clave' => false])->save();
        Auth::logoutOtherDevices($datos['clave']);
        $request->session()->regenerate();

        return redirect()->route('conductor.inicio')->with('aviso', 'Clave actualizada.');
    }
}
