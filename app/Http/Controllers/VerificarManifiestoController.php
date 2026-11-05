<?php

namespace App\Http\Controllers;

use App\Models\ManifiestoCarga;
use App\Support\Pdf\PdfManifiestoCarga;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Verificación pública de un manifiesto (código QR) y descarga del PDF. */
class VerificarManifiestoController extends Controller
{
    public function mostrar(string $codigo): View
    {
        return view('manifiesto.verificar', ['m' => $this->manifiesto($codigo)]);
    }

    public function pdf(string $codigo): Response
    {
        $m = $this->manifiesto($codigo);

        return response(PdfManifiestoCarga::pdf($m), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.PdfManifiestoCarga::nombre($m).'"',
        ]);
    }

    private function manifiesto(string $codigo): ManifiestoCarga
    {
        return ManifiestoCarga::query()->withoutGlobalScopes()->where('codigo_verificacion', $codigo)->firstOrFail();
    }
}
