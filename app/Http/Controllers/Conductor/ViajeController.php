<?php

namespace App\Http\Controllers\Conductor;

use App\Actions\Operacion\PlanificarServicio;
use App\Http\Controllers\Controller;
use App\Models\Conductor;
use App\Models\Servicio;
use App\Support\Pdf\PdfPlanViaje;
use App\Support\Pesv\ServiciosConductor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Plan de viaje en el portal del conductor: ver el rutograma, marcar «Leí el plan» y, si quiere,
 * reportar inicio, paradas, novedades y fin del viaje (opcional, decisión del usuario).
 */
class ViajeController extends Controller
{
    public function mostrar(Request $request, Servicio $servicio): View
    {
        $this->conductor($request, $servicio);
        $servicio->load(['ruta', 'vehiculo', 'conductores', 'origen', 'destino', 'fuec', 'eventos.conductor']);

        return view('conductor.servicio', ['s' => $servicio]);
    }

    public function pdf(Request $request, Servicio $servicio): Response
    {
        $this->conductor($request, $servicio);

        return response(PdfPlanViaje::servicio($servicio), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . PdfPlanViaje::nombre($servicio) . '"',
        ]);
    }

    public function reportar(Request $request, Servicio $servicio, PlanificarServicio $planificar): RedirectResponse
    {
        $conductor = $this->conductor($request, $servicio);
        $datos = $request->validate([
            'tipo' => ['required', 'in:leido,inicio,parada,novedad,fin'],
            'nota' => ['nullable', 'string', 'max:1000'],
            'kilometraje' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'latitud' => ['nullable', 'numeric'],
            'longitud' => ['nullable', 'numeric'],
        ]);

        try {
            $planificar->reportar($servicio, $conductor, $datos['tipo'], $datos);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $mensajes = [
            'leido' => 'Listo: quedó registrado que leyó el plan de viaje. ¡Buen viaje!',
            'inicio' => 'Viaje iniciado. Respete las velocidades y las paradas del plan.',
            'parada' => 'Parada registrada.',
            'novedad' => 'Novedad enviada a la empresa.',
            'fin' => 'Viaje finalizado. Gracias.',
        ];

        return redirect()->route('conductor.servicio', $servicio)->with('aviso', $mensajes[$datos['tipo']]);
    }

    /** Conductor del usuario que va en el servicio (404 si no es suyo o está cancelado). */
    private function conductor(Request $request, Servicio $servicio): Conductor
    {
        $ids = ServiciosConductor::conductores($request->user())->pluck('id');
        $conductor = $servicio->conductores()->whereIn('conductores.id', $ids)->first();

        abort_unless($conductor && ! $servicio->cancelado(), 404);

        return $conductor;
    }
}
