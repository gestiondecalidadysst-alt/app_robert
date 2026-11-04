<?php

namespace App\Http\Controllers\Conductor;

use App\Actions\Pesv\GuardarFirmaConductor;
use App\Http\Controllers\Controller;
use App\Models\Fuec;
use App\Support\Pdf\PdfFuec;
use App\Support\Pesv\ServiciosConductor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Inicio del portal (tarjetas de servicios), firma única del conductor y extracto (FUEC).
 */
class PortalController extends Controller
{
    public function inicio(Request $request): View
    {
        $usuario = $request->user();
        $conductores = ServiciosConductor::conductores($usuario);

        return view('conductor.inicio', [
            'usuario' => $usuario,
            'conductores' => $conductores,
            // La firma se usa en los preoperacionales: solo se pide si la empresa tiene seguridad vial.
            'sinFirma' => $conductores->reject->tieneFirma()->filter(fn ($c) => \App\Support\Modulos::activo($c->empresa_id, \App\Enums\ModuloEmpresa::SeguridadVial)),
            'tarjetas' => ServiciosConductor::fuecs($usuario)->map(fn (Fuec $f) => ServiciosConductor::tarjeta($f)),
            'vehiculos' => ServiciosConductor::vehiculosAsignados($usuario),
        ]);
    }

    public function formularioFirma(Request $request): View|RedirectResponse
    {
        $pendientes = ServiciosConductor::conductores($request->user())->reject->tieneFirma();

        if ($pendientes->isEmpty()) {
            return redirect()->route('conductor.inicio');
        }

        return view('conductor.firma');
    }

    /** El conductor registra su firma una sola vez (si ya tiene, solo la empresa la puede cambiar). */
    public function guardarFirma(Request $request, GuardarFirmaConductor $guardar): RedirectResponse
    {
        $request->validate(['firma' => ['required', 'string'], 'acepto' => ['accepted']], [
            'acepto.accepted' => 'Debe aceptar el uso de su firma en los preoperacionales.',
        ]);

        $pendientes = ServiciosConductor::conductores($request->user())->reject->tieneFirma();

        try {
            foreach ($pendientes as $conductor) {
                $guardar($conductor, $request->string('firma')->toString(), 'desde el portal del conductor');
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->intended(route('conductor.inicio'))->with('aviso', 'Firma registrada. Se pondrá en cada preoperacional que haga.');
    }

    /** Extracto: solo el día del servicio y con preoperacional apto del vehículo. */
    public function extracto(Request $request, Fuec $fuec): Response|RedirectResponse
    {
        abort_unless(ServiciosConductor::esSuyo($request->user(), $fuec) && ! $fuec->anulado(), 404);

        $tarjeta = ServiciosConductor::tarjeta($fuec);
        if (! $tarjeta['puede_extracto']) {
            return redirect()->route('conductor.inicio')->withErrors(['extracto' => $tarjeta['motivo_extracto']]);
        }

        return response(PdfFuec::generar($fuec), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . PdfFuec::nombreArchivo($fuec) . '"',
        ]);
    }
}
