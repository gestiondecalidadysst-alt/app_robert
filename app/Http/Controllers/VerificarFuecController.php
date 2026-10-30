<?php

namespace App\Http\Controllers;

use App\Models\Fuec;
use Illuminate\View\View;

/**
 * Verificación pública del FUEC (al escanear el QR del documento). No requiere sesión.
 * Muestra solo lo necesario para que una autoridad compare con el documento físico.
 */
class VerificarFuecController extends Controller
{
    public function __invoke(string $codigo): View
    {
        $fuec = Fuec::query()->withoutGlobalScopes()->where('codigo_verificacion', $codigo)->first();

        abort_if(! $fuec, 404);

        return view('fuec.verificar', ['fuec' => $fuec, 'd' => $fuec->datos]);
    }
}
