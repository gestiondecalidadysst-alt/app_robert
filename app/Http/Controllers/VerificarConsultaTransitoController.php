<?php

namespace App\Http\Controllers;

use App\Models\ConsultaTransito;
use App\Support\Pdf\PdfConsultaTransito;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Verificación pública de una constancia de consulta de tránsito (código QR). Muestra lo mínimo
 * (sin nombres ni documentos completos) y permite descargar la constancia.
 */
class VerificarConsultaTransitoController extends Controller
{
    public function mostrar(string $codigo): View
    {
        return view('transito.verificar', ['c' => $this->consulta($codigo)]);
    }

    public function pdf(string $codigo): Response
    {
        $consulta = $this->consulta($codigo);

        return response(PdfConsultaTransito::constancia($consulta), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.PdfConsultaTransito::nombre($consulta).'"',
        ]);
    }

    private function consulta(string $codigo): ConsultaTransito
    {
        $consulta = ConsultaTransito::query()->withoutGlobalScopes()->where('codigo_verificacion', $codigo)->firstOrFail();
        abort_unless($consulta->tieneConstancia(), 404);

        return $consulta;
    }
}
