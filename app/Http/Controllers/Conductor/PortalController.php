<?php

namespace App\Http\Controllers\Conductor;

use App\Actions\Pesv\GuardarFirmaConductor;
use App\Enums\ModuloEmpresa;
use App\Http\Controllers\Controller;
use App\Models\Fuec;
use App\Models\ManifiestoCarga;
use App\Support\Modulos;
use App\Support\Pdf\Imagenes;
use App\Support\Pdf\PdfFuec;
use App\Support\Pesv\ServiciosConductor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
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
            'sinFirma' => $conductores->reject->tieneFirma()->filter(fn ($c) => Modulos::activo($c->empresa_id, ModuloEmpresa::SeguridadVial)),
            'tarjetas' => ServiciosConductor::fuecs($usuario)->map(fn (Fuec $f) => ServiciosConductor::tarjeta($f)),
            'vehiculos' => ServiciosConductor::vehiculosAsignados($usuario),
            // Manifiestos de carga en curso: solo si la empresa tiene el módulo.
            'manifiestosCarga' => self::manifiestosCarga($conductores),
        ]);
    }

    /** Cuántos manifiestos de carga vigentes tiene el conductor pendientes de tiempos. */
    private static function manifiestosCarga(Collection $conductores): int
    {
        $ids = $conductores->filter(fn ($c) => Modulos::activo($c->empresa_id, ModuloEmpresa::Carga))->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return ManifiestoCarga::query()->withoutGlobalScopes()
            ->whereNull('anulado_at')->whereDoesntHave('cumplido')
            ->where(fn ($query) => $query->whereIn('conductor_id', $ids)->orWhereIn('conductor_2_id', $ids))
            ->count();
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
            'Content-Disposition' => 'inline; filename="'.PdfFuec::nombreArchivo($fuec).'"',
        ]);
    }

    /** Listado de pasajeros del extracto asignado, para mostrarlo en una revisión. */
    public function pasajeros(Request $request, Fuec $fuec): View
    {
        abort_unless(ServiciosConductor::esSuyo($request->user(), $fuec) && ! $fuec->anulado(), 404);
        abort_unless($fuec->tieneListadoPasajeros(), 404);

        $fuec->load('pasajeros');

        return view('conductor.pasajeros', [
            'fuec' => $fuec,
            'qr' => Imagenes::qr(route('listado.verificar', $fuec->pasajeros_codigo), 5),
        ]);
    }
}
