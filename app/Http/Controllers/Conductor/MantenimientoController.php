<?php

namespace App\Http\Controllers\Conductor;

use App\Actions\Mantenimiento\RegistrarMantenimiento;
use App\Enums\AccionMantenimiento;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Mantenimiento;
use App\Models\PlanAccion;
use App\Models\Vehiculo;
use App\Support\Mantenimiento\PlanPreventivo;
use App\Support\Pesv\ServiciosConductor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Portal del conductor: planes de acción pendientes de sus vehículos y registro de mantenimientos
 * con evidencia (fotos o factura), que la empresa verifica.
 */
class MantenimientoController extends Controller
{
    public function inicio(Request $request): View
    {
        $vehiculos = $this->vehiculos($request);

        return view('conductor.mantenimiento', [
            'vehiculos' => $vehiculos,
            'planes' => PlanAccion::query()->withoutGlobalScopes()->whereIn('vehiculo_id', $vehiculos->pluck('id'))->pendientes()
                ->with('vehiculo')->orderByRaw("case prioridad when 'alta' then 0 when 'media' then 1 else 2 end")->latest('id')->get(),
            'programadas' => \App\Models\ActividadMantenimiento::query()->withoutGlobalScopes()
                ->whereIn('vehiculo_id', $vehiculos->pluck('id'))->pendientes()
                ->whereHas('programa', fn ($q) => $q->whereIn('estado', ['aprobado', 'borrador']))
                ->whereDate('fecha_programada', '<=', today()->addDays(30))
                ->with('vehiculo')->orderBy('fecha_programada')->limit(10)->get(),
            'recientes' => Mantenimiento::query()->withoutGlobalScopes()
                ->whereIn('conductor_id', ServiciosConductor::conductores($request->user())->pluck('id'))
                ->with(['vehiculo', 'plan'])->withCount('evidencias')->latest('id')->limit(15)->get(),
        ]);
    }

    public function formulario(Request $request, Vehiculo $vehiculo): View
    {
        abort_unless(ServiciosConductor::puedeInspeccionar($request->user(), $vehiculo), 404);

        $planes = PlanAccion::query()->withoutGlobalScopes()->where('vehiculo_id', $vehiculo->id)->pendientes()->latest('id')->get();
        $plan = $planes->firstWhere('id', $request->integer('plan'));

        return view('conductor.mantenimiento-registrar', [
            'vehiculo' => $vehiculo,
            'planes' => $planes,
            'plan' => $plan,
            'rutinas' => PlanPreventivo::para($vehiculo),
            'acciones' => AccionMantenimiento::cases(),
            'kmActual' => $vehiculo->kilometrajeActual(),
        ]);
    }

    public function guardar(Request $request, Vehiculo $vehiculo, RegistrarMantenimiento $registrar): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless(ServiciosConductor::puedeInspeccionar($usuario, $vehiculo), 404);
        $conductor = ServiciosConductor::conductorPara($usuario, $vehiculo->empresa_id);
        abort_unless($conductor, 404);

        $request->validate([
            'archivos' => ['array', 'max:10'],
            'archivos.*' => ['file', 'max:10240'],
        ], ['archivos.*.max' => 'Cada archivo puede pesar máximo 10 MB.', 'archivos.max' => 'Máximo 10 archivos.']);

        try {
            $registrar(
                Empresa::findOrFail($vehiculo->empresa_id),
                $vehiculo,
                $request->only(['fecha', 'kilometraje', 'tipo', 'accion', 'actividad', 'problema', 'repuestos', 'taller', 'valor', 'factura_numero', 'rutinas', 'plan_accion_id']),
                array_values($request->file('archivos', [])),
                $usuario,
                $conductor,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('conductor.mantenimiento')->with('aviso', "Mantenimiento del vehículo {$vehiculo->placa} registrado. La empresa revisará la evidencia.");
    }

    /** Vehículos a los que el conductor puede registrarles mantenimiento (asignados o con FUEC vigente). */
    private function vehiculos(Request $request): Collection
    {
        $usuario = $request->user();

        return ServiciosConductor::vehiculosAsignados($usuario)->pluck('vehiculo')
            ->merge(ServiciosConductor::fuecs($usuario)->filter(fn ($f) => $f->estado()->value === 'vigente')->pluck('vehiculo'))
            ->filter()->unique('id')->values();
    }
}
