<?php

namespace App\Http\Controllers;

use App\Models\DocumentoContrato;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verificación pública del contrato (QR del certificado de firma). Permite comprobar que un
 * PDF no fue alterado: se sube y se compara su huella SHA-256 con la registrada al firmar.
 */
class VerificarContratoController extends Controller
{
    public function mostrar(string $codigo): View
    {
        return view('contrato.verificar', ['documento' => $this->documento($codigo), 'resultado' => null]);
    }

    public function archivo(Request $request, string $codigo): View
    {
        $documento = $this->documento($codigo);

        $request->validate(
            ['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']],
            ['archivo.required' => 'Elija el archivo PDF del contrato.', 'archivo.mimes' => 'El archivo debe ser un PDF.'],
        );

        $huella = hash_file('sha256', $request->file('archivo')->getRealPath());

        return view('contrato.verificar', [
            'documento' => $documento,
            'resultado' => $documento->huella_pdf !== null && hash_equals($documento->huella_pdf, $huella),
        ]);
    }

    private function documento(string $codigo): DocumentoContrato
    {
        return DocumentoContrato::query()->withoutGlobalScopes()->where('codigo_verificacion', $codigo)->first() ?? abort(404);
    }
}
