<?php

namespace App\Http\Controllers;

use App\Actions\Contratos\FirmarDocumentoContrato;
use App\Actions\Contratos\SolicitarCodigoFirma;
use App\Enums\EstadoFirma;
use App\Models\DocumentoContrato;
use App\Models\Empresa;
use App\Support\Contacto;
use App\Support\Pdf\PdfContrato;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Página pública de firma del contrato (enlace enviado por SMS o correo). No requiere sesión:
 * el enlace es único, difícil de adivinar y vence; la identidad se confirma con un código.
 */
class FirmaContratoController extends Controller
{
    public function mostrar(Request $request, string $token): Response
    {
        $documento = DocumentoContrato::porToken($token);

        if (! $documento) {
            return response()->view('firma.invalido', [], 404);
        }

        if ($documento->estado === EstadoFirma::Enviado && $documento->admiteFirma()) {
            $documento->forceFill(['estado' => EstadoFirma::Visto, 'visto_at' => now()])->save();
            $documento->registrar('visto', [], $request->ip(), $request->userAgent());
        }

        $empresa = Empresa::find($documento->empresa_id);
        $codigoActivo = $documento->codigos()->whereNull('usado_at')->latest('id')->first();

        return response()->view('firma.documento', [
            'documento' => $documento,
            'token' => $token,
            'empresa' => $documento->datos['empresa'] ?? [],
            'canales' => $documento->admiteFirma() ? SolicitarCodigoFirma::canales($documento, $empresa) : [],
            'destinos' => [
                'sms' => Contacto::ocultarCelular($documento->firmante_celular),
                'correo' => Contacto::ocultarCorreo($documento->firmante_email),
            ],
            'codigoActivo' => $codigoActivo?->disponible() ? $codigoActivo : null,
            'segundosReenvio' => $codigoActivo ? max(0, 60 - (int) $codigoActivo->created_at->diffInSeconds(now())) : 0,
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function codigo(Request $request, string $token): RedirectResponse
    {
        $documento = DocumentoContrato::porToken($token) ?? abort(404);

        try {
            $enviado = app(SolicitarCodigoFirma::class)(
                $documento,
                $request->input('canal'),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (ValidationException $e) {
            return redirect()->to(route('contrato.firma', $token) . '#firmar')->withErrors($e->errors());
        }

        return redirect()->to(route('contrato.firma', $token) . '#firmar')
            ->with('codigo_enviado', ($enviado['canal'] === 'sms' ? 'por SMS al celular ' : 'al correo ') . $enviado['destino']);
    }

    public function firmar(Request $request, string $token): RedirectResponse
    {
        $documento = DocumentoContrato::porToken($token) ?? abort(404);

        $request->validate([
            'codigo' => ['required', 'string', 'max:10'],
            'firma' => ['required', 'string', 'max:900000'],
        ], [
            'codigo.required' => 'Escriba el código que le enviamos.',
            'firma.required' => 'Dibuje su firma en el recuadro.',
        ]);

        try {
            app(FirmarDocumentoContrato::class)(
                $documento,
                (string) $request->input('codigo'),
                $request->input('firma'),
                $request->boolean('acepto'),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (ValidationException $e) {
            return redirect()->to(route('contrato.firma', $token) . '#firmar')->withErrors($e->errors())->withInput($request->only('acepto'));
        }

        return redirect()->route('contrato.firma', $token)->with('firmado', true);
    }

    public function pdf(string $token): Response
    {
        $documento = DocumentoContrato::porToken($token) ?? abort(404);

        if ($documento->firmado() && $documento->pdf_firmado_path) {
            return Storage::disk(DocumentoContrato::DISCO)->download($documento->pdf_firmado_path, $documento->nombreArchivo(), [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return response(PdfContrato::documento($documento), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $documento->nombreArchivo() . '"',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
