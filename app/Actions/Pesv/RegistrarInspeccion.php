<?php

namespace App\Actions\Pesv;

use App\Enums\EstadoVigencia;
use App\Enums\ResultadoInspeccion;
use App\Enums\TipoInspeccion;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\Fuec;
use App\Models\Inspeccion;
use App\Models\ItemChequeo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Nit;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Única forma de registrar un preoperacional o una inspección mensual.
 *
 * - Preoperacional: lo hace el conductor desde el portal, con su usuario. No firma cada vez: se
 *   copia la firma que registró una sola vez y queda la evidencia de su ingreso (fecha, IP,
 *   dispositivo y ubicación si la compartió).
 * - Mensual: la registra la empresa; el conductor es opcional.
 * - Resultado: "no apto" si falla algún ítem crítico. Cada ítem que falla exige observación.
 */
class RegistrarInspeccion
{
    public const RESPUESTAS = ['si' => 'Cumple', 'no' => 'No cumple', 'na' => 'No aplica'];

    /**
     * @param  array<int|string, array{respuesta?: ?string, observacion?: ?string}>  $respuestas  por id de ítem
     * @param  array{ip?: ?string, user_agent?: ?string, latitud?: mixed, longitud?: mixed}  $contexto
     */
    public function __invoke(
        Empresa $empresa,
        TipoInspeccion $tipo,
        Vehiculo $vehiculo,
        array $respuestas,
        ?Conductor $conductor = null,
        ?Fuec $fuec = null,
        ?int $kilometraje = null,
        ?string $observaciones = null,
        array $contexto = [],
        ?User $usuario = null,
        ?\DateTimeInterface $fecha = null,
    ): Inspeccion {
        $fecha = $fecha ? \Illuminate\Support\Carbon::instance($fecha)->startOfDay() : today();
        $preoperacional = $tipo === TipoInspeccion::Preoperacional;
        $errores = [];

        if ($vehiculo->empresa_id !== $empresa->id) {
            $errores['vehiculo'] = 'El vehículo no es de esta empresa.';
        }
        if ($conductor && $conductor->empresa_id !== $empresa->id) {
            $errores['conductor'] = 'El conductor no es de esta empresa.';
        }

        if ($preoperacional) {
            if (! $conductor) {
                $errores['conductor'] = 'El preoperacional lo registra el conductor.';
            } elseif (! $conductor->tieneFirma()) {
                $errores['conductor'] = filled($conductor->firma_path)
                    ? 'No se encontró el archivo de la firma del conductor. Regístrela de nuevo antes de hacer el preoperacional.'
                    : 'El conductor no tiene firma registrada. Regístrela antes de hacer el preoperacional.';
            }
            if ($kilometraje === null) {
                $errores['kilometraje'] = 'Escriba el kilometraje actual.';
            }
            if (! isset($errores['vehiculo']) && $vehiculo->preoperacionalDelDia($fecha)?->apta()) {
                $errores['vehiculo'] = "El preoperacional de hoy del vehículo {$vehiculo->placa} ya está hecho.";
            }
        }

        if ($fuec) {
            if ($fuec->empresa_id !== $empresa->id || $fuec->vehiculo_id !== $vehiculo->id) {
                $errores['fuec'] = 'El FUEC no corresponde a este vehículo.';
            } elseif ($fuec->estado($fecha) !== EstadoVigencia::Vigente) {
                $errores['fuec'] = 'El FUEC no está vigente hoy.';
            } elseif ($conductor && ! $fuec->conductores()->whereKey($conductor->id)->exists()) {
                $errores['fuec'] = 'El conductor no está en este FUEC.';
            }
        }

        $items = ItemChequeo::query()->deEmpresa($empresa)->deTipo($tipo)->activos()->ordenados()->get();
        if ($items->isEmpty()) {
            $errores['respuestas'] = 'La empresa no tiene ítems de chequeo activos para este tipo de inspección.';
        }

        $copia = [];
        $faltan = 0;
        foreach ($items as $item) {
            $respuesta = $respuestas[$item->id]['respuesta'] ?? null;
            $observacion = trim((string) ($respuestas[$item->id]['observacion'] ?? '')) ?: null;

            if (! array_key_exists((string) $respuesta, self::RESPUESTAS)) {
                $faltan++;

                continue;
            }
            if ($respuesta === 'no' && ! $observacion) {
                $errores["respuestas.{$item->id}.observacion"] = "Explique la falla en «{$item->descripcion}».";
            }

            $copia[] = [
                'item_id' => $item->id,
                'grupo' => $item->grupo,
                'descripcion' => $item->descripcion,
                'critico' => $item->critico,
                'respuesta' => $respuesta,
                'observacion' => $observacion ? Str::limit($observacion, 500, '') : null,
            ];
        }
        if ($faltan > 0) {
            $errores['respuestas'] = $faltan === 1 ? 'Falta responder 1 ítem.' : "Faltan responder {$faltan} ítems.";
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        $noApto = collect($copia)->contains(fn (array $r) => $r['critico'] && $r['respuesta'] === 'no');

        // Un plan de acción de prioridad alta pendiente (falla crítica sin corregir) deja el vehículo NO APTO.
        // Solo si la empresa tiene el módulo de mantenimiento (sin él no hay cómo cerrar el plan).
        $conMantenimiento = \App\Support\Modulos::activo($empresa->id, \App\Enums\ModuloEmpresa::Mantenimiento);
        $planAlta = $preoperacional && $conMantenimiento ? $vehiculo->planAltaPendiente() : null;
        $noApto = $noApto || $planAlta !== null;

        $inspeccion = DB::transaction(function () use ($empresa, $tipo, $vehiculo, $conductor, $fuec, $fecha, $kilometraje, $observaciones, $contexto, $usuario, $copia, $noApto, $planAlta) {
            $codigo = Str::lower(Str::random(24));
            $firma = null;

            if ($conductor?->tieneFirma() && Storage::disk(GuardarFirmaConductor::DISCO)->exists($conductor->firma_path)) {
                $extension = pathinfo($conductor->firma_path, PATHINFO_EXTENSION) ?: 'png';
                $firma = "inspecciones/{$empresa->id}/{$fecha->format('Y/m')}/{$codigo}-firma.{$extension}";
                Storage::disk(Inspeccion::DISCO)->copy($conductor->firma_path, $firma);
            }

            $inspeccion = new Inspeccion([
                'tipo' => $tipo,
                'vehiculo_id' => $vehiculo->id,
                'conductor_id' => $conductor?->id,
                'fuec_id' => $fuec?->id,
                'fecha' => $fecha->toDateString(),
                'kilometraje' => $kilometraje,
                'resultado' => $noApto ? ResultadoInspeccion::NoApto : ResultadoInspeccion::Apto,
                'respuestas' => $copia,
                'datos' => [...self::datos($empresa, $vehiculo, $conductor, $fuec, $usuario), 'plan_pendiente' => $planAlta?->numero],
                'observaciones' => filled($observaciones) ? Str::limit(trim($observaciones), 2000, '') : null,
                'firma_path' => $firma,
                'codigo_verificacion' => $codigo,
                'ip' => $contexto['ip'] ?? null,
                'user_agent' => isset($contexto['user_agent']) ? Str::limit((string) $contexto['user_agent'], 500, '') : null,
                'latitud' => self::coordenada($contexto['latitud'] ?? null, 90),
                'longitud' => self::coordenada($contexto['longitud'] ?? null, 180),
                'user_id' => $usuario?->id,
            ]);
            $inspeccion->empresa_id = $empresa->id;
            $inspeccion->save();

            return $inspeccion;
        });

        // Fallas → plan de acción; nuevo kilometraje → revisa el preventivo. Un error aquí no deshace la inspección.
        try {
            if ($conMantenimiento) {
                \App\Actions\Mantenimiento\PlanesDesdeInspeccion::procesar($inspeccion);
            }
            if ($conMantenimiento && $kilometraje !== null) {
                \App\Actions\Mantenimiento\SeguimientoPreventivo::revisar($vehiculo);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if ($noApto) {
            self::avisarNoApto($empresa, $inspeccion);
            \App\Support\Webhooks::emitir($empresa, 'preoperacional.no_apto', [
                'inspeccion_id' => $inspeccion->id,
                'placa' => $vehiculo->placa,
                'conductor' => $inspeccion->conductor_nombre,
                'fecha' => $inspeccion->created_at->toIso8601String(),
                'fallas' => collect($inspeccion->fallas())->map(fn ($f) => ['descripcion' => $f['descripcion'], 'critico' => (bool) $f['critico'], 'observacion' => $f['observacion']])->all(),
                'plan_pendiente' => $inspeccion->datos['plan_pendiente'] ?? null,
                'verificacion' => $inspeccion->urlVerificacion(),
            ]);
        }

        return $inspeccion;
    }

    private static function datos(Empresa $empresa, Vehiculo $vehiculo, ?Conductor $conductor, ?Fuec $fuec, ?User $usuario): array
    {
        $licencia = $conductor?->licencia();

        return [
            'empresa' => [
                'razon_social' => $empresa->razon_social,
                'nombre_comercial' => $empresa->nombre_comercial,
                'nit' => Nit::formatear($empresa->nit, $empresa->dv),
                'direccion' => $empresa->direccion,
                'telefono' => $empresa->telefono,
                'email' => $empresa->email,
                'logo_path' => $empresa->logo_path,
            ],
            'vehiculo' => [
                'placa' => $vehiculo->placa,
                'numero_interno' => $vehiculo->numero_interno,
                'marca' => $vehiculo->marca?->nombre,
                'linea' => $vehiculo->linea?->nombre,
                'modelo' => $vehiculo->modelo,
                'clase' => $vehiculo->claseVehiculo?->nombre,
            ],
            'conductor' => $conductor ? [
                'nombre' => $conductor->nombre_completo,
                'documento' => $conductor->documento,
                'licencia' => $licencia?->numero,
                'categoria' => $licencia?->categoriaLicencia?->codigo,
            ] : null,
            'fuec' => $fuec ? ['numero' => $fuec->numero, 'contrato' => $fuec->contrato?->numero_formateado] : null,
            'registrado_por' => $usuario?->name,
            'registrado_por_conductor' => $usuario && $conductor && $conductor->user_id === $usuario->id,
        ];
    }

    private static function coordenada(mixed $valor, int $limite): ?float
    {
        return is_numeric($valor) && abs((float) $valor) <= $limite && (float) $valor != 0.0 ? round((float) $valor, 7) : null;
    }

    /** Aviso en la campana a quienes pueden ver inspecciones en la empresa. */
    private static function avisarNoApto(Empresa $empresa, Inspeccion $inspeccion): void
    {
        $registrar = app(PermissionRegistrar::class);
        $anterior = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($empresa->id);

        $fallas = collect($inspeccion->fallas())->where('critico', true)->pluck('descripcion')->implode(', ');

        $empresa->usuarios()->wherePivot('activo', true)->where('users.activo', true)->get()
            ->filter(fn (User $u) => $u->unsetRelation('roles')->unsetRelation('permissions')->can('ver_inspecciones'))
            ->each(fn (User $u) => Notification::make()
                ->danger()
                ->title("Vehículo {$inspeccion->placa} no apto")
                ->body(($inspeccion->conductor_nombre ? "{$inspeccion->conductor_nombre}: " : '')
                    . ($fallas !== '' ? "falla en {$fallas}." : "plan de acción {$inspeccion->datos['plan_pendiente']} pendiente de cierre."))
                ->sendToDatabase($u));

        $registrar->setPermissionsTeamId($anterior);
    }
}
