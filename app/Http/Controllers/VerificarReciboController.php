<?php

namespace App\Http\Controllers;

use App\Models\PagoContrato;
use App\Support\Pdf\PdfRecibo;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Verificación pública de un recibo de caja (código QR del recibo y enlace enviado al cliente).
 * Muestra lo mínimo del recibo y permite descargarlo.
 */
class VerificarReciboController extends Controller
{
    public function mostrar(string $codigo): View
    {
        return view('recibo.verificar', ['p' => $this->pago($codigo)]);
    }

    public function pdf(string $codigo): Response
    {
        $pago = $this->pago($codigo);

        return response(PdfRecibo::recibo($pago), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.PdfRecibo::nombreRecibo($pago).'"',
        ]);
    }

    private function pago(string $codigo): PagoContrato
    {
        return PagoContrato::query()->withoutGlobalScopes()->with(['contrato' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('codigo_verificacion', $codigo)->firstOrFail();
    }
}
