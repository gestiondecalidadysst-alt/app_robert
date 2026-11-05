<?php

namespace App\Http\Controllers\Conductor;

use App\Enums\ModuloEmpresa;
use App\Http\Controllers\Controller;
use App\Models\Conductor;
use App\Models\ManifiestoCarga;
use App\Models\RemesaCarga;
use App\Models\TiempoLogistico;
use App\Support\Carga\TiemposLogisticos;
use App\Support\Modulos;
use App\Support\Pesv\ServiciosConductor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Tiempos logísticos desde el celular del conductor.
 *
 * El Ministerio los exige en tiempo real; pedírselos a la oficina al cerrar el viaje
 * es tarde y además sale mal. Aquí el conductor marca llegada, entrada y salida con
 * un toque, en el cargue y en el descargue, y el sistema toma la hora y la ubicación.
 */
class CargaController extends Controller
{
    public function inicio(Request $request): View
    {
        return view('conductor.carga', ['manifiestos' => $this->manifiestos($request)]);
    }

    public function marcar(Request $request, RemesaCarga $remesa): RedirectResponse
    {
        $conductor = $this->conductorDe($request, $remesa);

        $datos = $request->validate([
            'punto' => ['required', 'in:cargue,descargue'],
            'evento' => ['required', 'in:llegada,entrada,salida'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'observacion' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $tiempo = TiemposLogisticos::marcar($remesa, $datos['punto'], $datos['evento'], $conductor, null, [
                'latitud' => $datos['latitud'] ?? null,
                'longitud' => $datos['longitud'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('mensaje', $tiempo->etiqueta().' reportada a las '.$tiempo->ocurrido_at->format('h:i a').'.');
    }

    /** Manifiestos vigentes de este conductor, con sus remesas y lo que falta por marcar. */
    private function manifiestos(Request $request): Collection
    {
        $ids = ServiciosConductor::conductores($request->user())
            ->filter(fn (Conductor $c) => Modulos::activo($c->empresa_id, ModuloEmpresa::Carga))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return ManifiestoCarga::query()->withoutGlobalScopes()
            ->whereNull('anulado_at')
            ->whereDoesntHave('cumplido')
            ->where(fn ($query) => $query->whereIn('conductor_id', $ids)->orWhereIn('conductor_2_id', $ids))
            ->with(['origen', 'destino', 'vehiculo', 'remesas.remitente.municipio', 'remesas.destinatario.municipio', 'remesas.tiemposLogisticos'])
            ->orderByDesc('fecha_expedicion')->limit(20)->get()
            ->each(function (ManifiestoCarga $m): void {
                $m->remesas->each(function (RemesaCarga $r): void {
                    $siguiente = TiemposLogisticos::siguiente($r);
                    $r->setAttribute('siguiente_punto', $siguiente[0] ?? null);
                    $r->setAttribute('siguiente_evento', $siguiente[1] ?? null);
                    $r->setAttribute('marcados', TiemposLogisticos::registrados($r)
                        ->map(fn (TiempoLogistico $t) => $t->ocurrido_at->format('d/m h:i a')));
                });
            });
    }

    /** Que la remesa sea de un manifiesto asignado a este conductor. */
    private function conductorDe(Request $request, RemesaCarga $remesa): Conductor
    {
        $conductores = ServiciosConductor::conductores($request->user());
        $manifiesto = $remesa->manifiesto()->withoutGlobalScopes()->first();

        $conductor = $manifiesto
            ? $conductores->first(fn (Conductor $c) => in_array($c->id, [$manifiesto->conductor_id, $manifiesto->conductor_2_id], true))
            : null;

        abort_if(! $conductor, 403);

        return $conductor;
    }
}
