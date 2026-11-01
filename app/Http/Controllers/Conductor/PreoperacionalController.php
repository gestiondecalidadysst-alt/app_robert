<?php

namespace App\Http\Controllers\Conductor;

use App\Actions\Pesv\RegistrarInspeccion;
use App\Enums\TipoInspeccion;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Fuec;
use App\Models\Inspeccion;
use App\Models\ItemChequeo;
use App\Models\Vehiculo;
use App\Support\Pdf\PdfInspeccion;
use App\Support\Pesv\ServiciosConductor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Preoperacional desde el celular del conductor y sus PDF.
 */
class PreoperacionalController extends Controller
{
    public function formulario(Request $request, Vehiculo $vehiculo): View|RedirectResponse
    {
        $usuario = $request->user();
        abort_unless(ServiciosConductor::puedeInspeccionar($usuario, $vehiculo), 404);

        $conductor = ServiciosConductor::conductorPara($usuario, $vehiculo->empresa_id);
        abort_unless($conductor, 404);

        if (! $conductor->tieneFirma()) {
            redirect()->setIntendedUrl($request->fullUrl());

            return redirect()->route('conductor.firma');
        }

        $preop = $vehiculo->preoperacionalDelDia();
        if ($preop?->apta()) {
            return redirect()->route('conductor.inicio')->with('aviso', "El preoperacional de hoy del vehículo {$vehiculo->placa} ya está hecho.");
        }

        $fuec = $this->fuec($request, $vehiculo);
        $ultimoKm = Inspeccion::query()->deEmpresa($vehiculo->empresa_id)->where('vehiculo_id', $vehiculo->id)
            ->whereNotNull('kilometraje')->latest('id')->value('kilometraje');

        return view('conductor.preoperacional', [
            'vehiculo' => $vehiculo,
            'conductor' => $conductor,
            'fuec' => $fuec,
            'grupos' => ItemChequeo::query()->deEmpresa($vehiculo->empresa_id)->deTipo(TipoInspeccion::Preoperacional)
                ->activos()->ordenados()->get()->groupBy('grupo'),
            'ultimoKm' => $ultimoKm,
            'anterior' => $preop,
        ]);
    }

    public function guardar(Request $request, Vehiculo $vehiculo, RegistrarInspeccion $registrar): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless(ServiciosConductor::puedeInspeccionar($usuario, $vehiculo), 404);
        $conductor = ServiciosConductor::conductorPara($usuario, $vehiculo->empresa_id);
        abort_unless($conductor, 404);

        $request->validate([
            'kilometraje' => ['required', 'integer', 'min:0', 'max:9999999'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'respuestas' => ['array'],
            'declaro' => ['accepted'],
        ], ['declaro.accepted' => 'Confirme que hizo la revisión.'], ['kilometraje' => 'kilometraje']);

        try {
            $inspeccion = $registrar(
                Empresa::findOrFail($vehiculo->empresa_id),
                TipoInspeccion::Preoperacional,
                $vehiculo,
                (array) $request->input('respuestas', []),
                conductor: $conductor,
                fuec: $this->fuec($request, $vehiculo),
                kilometraje: (int) $request->input('kilometraje'),
                observaciones: $request->input('observaciones'),
                contexto: [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'latitud' => $request->input('latitud'),
                    'longitud' => $request->input('longitud'),
                ],
                usuario: $usuario,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('conductor.inicio')->with(
            $inspeccion->apta() ? 'aviso' : 'alerta',
            $inspeccion->apta()
                ? "Preoperacional del vehículo {$vehiculo->placa} registrado: APTO."
                : "Vehículo {$vehiculo->placa} NO APTO: no debe operar. La empresa ya fue avisada.",
        );
    }

    public function historial(Request $request): View
    {
        $ids = ServiciosConductor::conductores($request->user())->pluck('id');

        return view('conductor.historial', [
            'inspecciones' => Inspeccion::query()->withoutGlobalScopes()->whereIn('conductor_id', $ids)
                ->latest('id')->limit(40)->get(),
        ]);
    }

    public function pdf(Request $request, Inspeccion $inspeccion): Response
    {
        $ids = ServiciosConductor::conductores($request->user())->pluck('id');
        abort_unless($inspeccion->conductor_id && $ids->contains($inspeccion->conductor_id), 404);

        return response(PdfInspeccion::generar($inspeccion), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . PdfInspeccion::nombreArchivo($inspeccion) . '"',
        ]);
    }

    /** FUEC del servicio (si viene de una tarjeta), solo si es del conductor y del vehículo. */
    private function fuec(Request $request, Vehiculo $vehiculo): ?Fuec
    {
        $id = $request->integer('fuec');
        if (! $id) {
            return null;
        }

        $fuec = Fuec::query()->withoutGlobalScopes()->find($id);

        return $fuec && $fuec->vehiculo_id === $vehiculo->id && ServiciosConductor::esSuyo($request->user(), $fuec) ? $fuec : null;
    }
}
