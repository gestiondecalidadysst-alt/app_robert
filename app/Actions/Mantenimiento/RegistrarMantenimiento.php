<?php

namespace App\Actions\Mantenimiento;

use App\Enums\AccionMantenimiento;
use App\Enums\EstadoPlan;
use App\Enums\OrigenPlan;
use App\Enums\TipoMantenimiento;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\Mantenimiento;
use App\Models\PlanAccion;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Avisos;
use App\Support\Mantenimiento\PlanPreventivo;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra un mantenimiento hecho al vehículo, con sus evidencias.
 *
 * - Lo registran el auxiliar, el rol Mantenimiento o el conductor (portal). Del conductor se exige
 *   al menos una evidencia (decisión del usuario).
 * - Rutinas preventivas cumplidas: cierran sus planes de acción preventivos pendientes.
 * - Plan de acción atendido: pasa a «Por verificar» (lo cierra la empresa).
 */
class RegistrarMantenimiento
{
    /**
     * @param  array{fecha?: mixed, kilometraje?: mixed, tipo?: mixed, accion?: mixed, actividad?: ?string, problema?: ?string,
     *               repuestos?: ?string, taller?: ?string, responsable_documento?: ?string, responsable_nombre?: ?string,
     *               valor?: mixed, factura_numero?: ?string, rutinas?: list<int>, plan_accion_id?: ?int}  $datos
     * @param  list<UploadedFile>  $archivos
     */
    public function __invoke(Empresa $empresa, Vehiculo $vehiculo, array $datos, array $archivos = [], ?User $usuario = null, ?Conductor $conductor = null): Mantenimiento
    {
        $errores = [];
        $fecha = filled($datos['fecha'] ?? null) ? Carbon::parse($datos['fecha'])->startOfDay() : null;
        $tipo = ($datos['tipo'] ?? null) instanceof TipoMantenimiento ? $datos['tipo'] : TipoMantenimiento::tryFrom((string) ($datos['tipo'] ?? ''));
        $accion = ($datos['accion'] ?? null) instanceof AccionMantenimiento ? $datos['accion'] : AccionMantenimiento::tryFrom((string) ($datos['accion'] ?? ''));
        $km = filled($datos['kilometraje'] ?? null) ? (int) $datos['kilometraje'] : null;

        if ($vehiculo->empresa_id !== $empresa->id) {
            $errores['vehiculo_id'] = 'El vehículo no es de esta empresa.';
        }
        if (! $fecha) {
            $errores['fecha'] = 'Indique la fecha del mantenimiento.';
        } elseif ($fecha->gt(today())) {
            $errores['fecha'] = 'La fecha no puede ser futura.';
        }
        if (! $tipo) {
            $errores['tipo'] = 'Indique si es preventivo o correctivo.';
        }
        if (! $accion) {
            $errores['accion'] = 'Indique la acción realizada.';
        }
        if (blank($datos['actividad'] ?? null)) {
            $errores['actividad'] = 'Describa la actividad realizada.';
        }
        if ($km !== null && ($km < 0 || $km > 9999999)) {
            $errores['kilometraje'] = 'Kilometraje no válido.';
        }

        $rutinaIds = array_values(array_unique(array_map('intval', array_filter((array) ($datos['rutinas'] ?? [])))));
        $rutinas = PlanPreventivo::rutinasDe($vehiculo)->whereIn('id', $rutinaIds);
        if ($rutinas->count() !== count($rutinaIds)) {
            $errores['rutinas'] = 'Alguna rutina no aplica a este vehículo.';
        }
        if ($rutinaIds !== [] && $km === null) {
            $errores['kilometraje'] = 'Para registrar rutinas preventivas indique el kilometraje (desde él se calcula el próximo).';
        }

        $plan = null;
        if (filled($datos['plan_accion_id'] ?? null)) {
            $plan = PlanAccion::query()->deEmpresa($empresa)->where('vehiculo_id', $vehiculo->id)->pendientes()->find($datos['plan_accion_id']);
            if (! $plan) {
                $errores['plan_accion_id'] = 'El plan de acción no está pendiente para este vehículo.';
            }
        }

        if ($conductor && $archivos === []) {
            $errores['archivos'] = 'Adjunte al menos una evidencia (factura o foto del trabajo).';
        }
        if ($archivos !== []) {
            try {
                GuardarEvidencias::validar($archivos);
            } catch (ValidationException $e) {
                $errores += $e->errors();
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        $mantenimiento = DB::transaction(function () use ($empresa, $vehiculo, $datos, $fecha, $tipo, $accion, $km, $rutinas, $plan, $archivos, $usuario, $conductor) {
            $mantenimiento = new Mantenimiento([
                'vehiculo_id' => $vehiculo->id,
                'fecha' => $fecha->toDateString(),
                'kilometraje' => $km,
                'tipo' => $tipo,
                'accion' => $accion,
                'actividad' => trim((string) $datos['actividad']),
                'problema' => self::texto($datos['problema'] ?? null),
                'repuestos' => self::texto($datos['repuestos'] ?? null),
                'taller' => self::texto($datos['taller'] ?? null),
                'responsable_documento' => self::texto($datos['responsable_documento'] ?? null),
                'responsable_nombre' => self::texto($datos['responsable_nombre'] ?? null),
                'valor' => filled($datos['valor'] ?? null) ? (float) $datos['valor'] : null,
                'factura_numero' => self::texto($datos['factura_numero'] ?? null),
                'plan_accion_id' => $plan?->id,
                'conductor_id' => $conductor?->id,
                'user_id' => $usuario?->id,
            ]);
            $mantenimiento->empresa_id = $empresa->id;
            $mantenimiento->save();
            $mantenimiento->rutinas()->sync($rutinas->pluck('id')->all());

            if ($archivos !== []) {
                GuardarEvidencias::en($mantenimiento, $archivos, $usuario);
            }

            // Plan atendido: queda para que la empresa lo verifique.
            if ($plan && $plan->estado !== EstadoPlan::PorVerificar) {
                $plan->forceFill(['estado' => EstadoPlan::PorVerificar])->save();
            }

            // Rutinas cumplidas: cierran sus planes preventivos pendientes.
            $claves = $rutinas->map(fn ($r) => PlanPreventivo::clave($r->nombre))->all();
            PlanAccion::query()->deEmpresa($empresa)->where('vehiculo_id', $vehiculo->id)->pendientes()
                ->where('origen', OrigenPlan::Preventivo->value)->with('rutina')->get()
                ->filter(fn (PlanAccion $p) => $p->rutina && in_array(PlanPreventivo::clave($p->rutina->nombre), $claves, true))
                ->each(fn (PlanAccion $p) => $p->forceFill([
                    'estado' => EstadoPlan::Cerrado,
                    'cerrado_at' => now(),
                    'cerrado_por' => $usuario?->id,
                    'observacion_cierre' => "Cumplido con el mantenimiento del {$fecha->format('d/m/Y')} (" . ($km !== null ? number_format($km, 0, ',', '.') . ' km' : 'sin km') . ').',
                ])->save());

            return $mantenimiento;
        });

        if ($plan) {
            Avisos::aQuienPueda($empresa, 'editar_planes_accion', Notification::make()
                ->info()
                ->title("Plan {$plan->numero} ({$vehiculo->placa}) por verificar")
                ->body(($conductor ? "{$conductor->nombre_completo} registró" : 'Se registró') . ' el mantenimiento con su evidencia. Revíselo y ciérrelo.'));
        }

        SeguimientoPreventivo::revisar($vehiculo);

        return $mantenimiento;
    }

    private static function texto(mixed $valor): ?string
    {
        return filled($valor) ? trim((string) $valor) : null;
    }
}
