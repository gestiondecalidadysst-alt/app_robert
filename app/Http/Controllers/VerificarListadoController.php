<?php

namespace App\Http\Controllers;

use App\Models\Fuec;
use Illuminate\View\View;

/**
 * Verificación pública del listado de pasajeros de un FUEC (se llega por el QR).
 * Confirma que el listado es auténtico y muestra los pasajeros con el documento parcial.
 */
class VerificarListadoController extends Controller
{
    public function mostrar(string $codigo): View
    {
        $fuec = Fuec::query()->withoutGlobalScopes()->where('pasajeros_codigo', $codigo)->first() ?? abort(404);
        $fuec->load('pasajeros');

        $d = $fuec->datos ?? [];

        return view('listado.verificar', [
            'fuec' => $fuec,
            'anulado' => $fuec->anulado(),
            'empresa' => $d['empresa']['razon_social'] ?? $fuec->empresa?->razon_social ?? '',
            'ruta' => trim(($d['origen'] ?? '').' – '.($d['destino'] ?? ''), ' –'),
        ]);
    }
}
