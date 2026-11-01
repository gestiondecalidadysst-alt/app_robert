<?php

namespace App\Http\Controllers;

use App\Models\Inspeccion;
use Illuminate\View\View;

/**
 * Verificación pública de un preoperacional o inspección (código QR del PDF). Muestra lo mínimo:
 * fecha, vehículo, resultado y el documento del conductor oculto.
 */
class VerificarInspeccionController extends Controller
{
    public function __invoke(string $codigo): View
    {
        $inspeccion = Inspeccion::query()->withoutGlobalScopes()->where('codigo_verificacion', $codigo)->firstOrFail();

        return view('inspeccion.verificar', ['i' => $inspeccion]);
    }
}
