<?php

namespace App\Actions\Transito;

use App\Enums\EstadoComparendos;
use App\Enums\TipoConsultaTransito;
use App\Enums\TipoDocumentoVehiculo;
use App\Models\ComparendoTransito;
use App\Models\Conductor;
use App\Models\ConsultaTransito;
use App\Models\Empresa;
use App\Models\ProveedorTransito;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Transito\Interpretar;
use App\Services\Transito\PlacApi;
use App\Services\Transito\ResultadoConsulta;
use App\Support\Avisos;
use App\Support\Webhooks;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Hace una consulta de tránsito y guarda el resultado:
 * - Requisitos: módulo habilitado por la plataforma, tipo elegido por la empresa, proveedor
 *   configurado, precio definido y que no se pase del tope mensual de la empresa (si se pasa,
 *   se bloquea).
 * - Cobro: precio fijo por tipo. Solo se cobra lo que el proveedor cobró (no las consultas sin
 *   datos ni las que vienen de su caché).
 * - Resultado: comparendos (nuevos, cambios de estado, los que ya no aparecen), licencia (suspendida)
 *   o RUNT (SOAT y técnico-mecánica comparados con los documentos registrados). Avisa lo nuevo en
 *   la campana y por webhook.
 */
class ConsultarTransito
{
    public function __construct(private ?PlacApi $cliente = null) {}

    public function __invoke(Conductor|Vehiculo $sujeto, TipoConsultaTransito $tipo, string $origen = 'manual', ?User $usuario = null): ConsultaTransito
    {
        $empresa = Empresa::findOrFail($sujeto->empresa_id);
        $proveedor = ProveedorTransito::actual();
        [$parametros, $referencia] = $this->validar($empresa, $proveedor, $sujeto, $tipo);

        $resultado = ($this->cliente ?? new PlacApi($proveedor))->consultar($tipo, $parametros);
        $precio = $resultado->cobrada ? (float) $empresa->precioTransito($tipo) : 0.0;

        $consulta = ConsultaTransito::query()->create([
            'empresa_id' => $empresa->id,
            'tipo' => $tipo,
            'consultable_type' => $sujeto->getMorphClass(),
            'consultable_id' => $sujeto->getKey(),
            'referencia' => $referencia,
            'origen' => $origen,
            'user_id' => $usuario?->id ?? auth()->id(),
            'estado' => $resultado->estado,
            'creditos' => $resultado->cobrada ? $proveedor->creditosDe($tipo) : 0,
            'cobrada' => $resultado->cobrada,
            'precio' => $precio,
            'costo' => $resultado->cobrada ? $proveedor->costoDe($tipo) : 0,
            'desde_cache' => $resultado->desdeCache,
            'duracion_ms' => $resultado->duracionMs,
            'respuesta' => $resultado->datos ?: null,
            'error' => $resultado->error ? Str::limit($resultado->error, 1000) : null,
        ]);

        if ($resultado->estado !== 'error') {
            DB::transaction(fn () => $this->aplicar($empresa, $sujeto, $tipo, $resultado));
        }

        return $consulta;
    }

    /** Precio que se cobrará y si se puede consultar (para mostrar en la confirmación). */
    public static function cotizar(Conductor|Vehiculo $sujeto, TipoConsultaTransito $tipo): array
    {
        $empresa = Empresa::findOrFail($sujeto->empresa_id);
        $precio = $empresa->precioTransito($tipo);
        $gastado = $empresa->gastoTransitoDelMes();

        return [
            'precio' => $precio,
            'gastado' => $gastado,
            'tope' => $empresa->transito_tope_mensual !== null ? (float) $empresa->transito_tope_mensual : null,
        ];
    }

    /** @return array{0: array<string, string>, 1: string} parámetros para el proveedor y referencia */
    private function validar(Empresa $empresa, ProveedorTransito $proveedor, Conductor|Vehiculo $sujeto, TipoConsultaTransito $tipo): array
    {
        $error = fn (string $m) => throw ValidationException::withMessages(['consulta' => $m]);

        if (! $empresa->transito_habilitado) {
            $error('El módulo de consultas de tránsito no está habilitado para esta empresa.');
        }
        if (! $proveedor->configurado()) {
            $error('Las consultas de tránsito no están disponibles en este momento (proveedor sin configurar).');
        }
        if (! $empresa->usaTransito($tipo)) {
            $error("La empresa no tiene activa la consulta «{$tipo->getLabel()}» (Configuración → Integraciones).");
        }
        if ($tipo->esDeConductor() !== ($sujeto instanceof Conductor)) {
            $error('Ese tipo de consulta no corresponde a este registro.');
        }
        $precio = $empresa->precioTransito($tipo);
        if ($precio === null) {
            $error('La plataforma no ha definido el precio de esta consulta.');
        }
        if ($empresa->transito_tope_mensual !== null && $empresa->gastoTransitoDelMes() + $precio > (float) $empresa->transito_tope_mensual) {
            $error('Se alcanzó el tope mensual de consultas ($ '.number_format((float) $empresa->transito_tope_mensual, 0, ',', '.')
                .'). Súbalo en Configuración → Integraciones para seguir consultando.');
        }

        if ($sujeto instanceof Conductor) {
            $sujeto->loadMissing('tipoIdentificacion');
            $parametros = ['docType' => $sujeto->tipoIdentificacion?->codigo ?? 'CC', 'docNumber' => (string) $sujeto->documento];
            if ($tipo === TipoConsultaTransito::ConductorLicencia) {
                $parametros['primerApellido'] = Str::of((string) $sujeto->apellidos)->trim()->before(' ')->upper()->toString();
            }

            return [$parametros, (string) $sujeto->documento];
        }

        $propietario = $sujeto->propietario()->withoutGlobalScopes()->with('tipoIdentificacion')->first();
        if (! $propietario?->documento) {
            $error("El vehículo {$sujeto->placa} no tiene propietario con documento: el RUNT y el SIMIT lo exigen para consultar por placa.");
        }

        return [[
            'placa' => Str::upper((string) $sujeto->placa),
            'docType' => $propietario->tipoIdentificacion?->codigo ?? 'CC',
            'docNumber' => (string) $propietario->documento,
        ], (string) $sujeto->placa];
    }

    private function aplicar(Empresa $empresa, Conductor|Vehiculo $sujeto, TipoConsultaTransito $tipo, ResultadoConsulta $r): void
    {
        $resumen = $sujeto->transito_resumen ?? [];
        $ahora = now();

        if (in_array($tipo, [TipoConsultaTransito::ConductorComparendos, TipoConsultaTransito::VehiculoMultas], true)) {
            $datos = $r->ok() ? Interpretar::comparendos($r->datos) : ['comparendos' => [], 'deuda' => 0.0];
            $nuevos = $this->guardarComparendos($empresa, $sujeto, $datos['comparendos']);
            $vigentes = collect($datos['comparendos']);
            $resumen['comparendos'] = [
                'deuda' => $datos['deuda'],
                'pendientes' => $vigentes->where('estado', 'pendiente')->count(),
                'acuerdos' => $vigentes->where('estado', 'acuerdo')->count(),
                'total' => $vigentes->count(),
                'consultado_at' => $ahora->toIso8601String(),
            ];

            if ($sujeto instanceof Conductor) {
                $sujeto->forceFill([
                    'comparendos_estado' => match (true) {
                        $resumen['comparendos']['pendientes'] > 0 => EstadoComparendos::Pendiente,
                        $resumen['comparendos']['acuerdos'] > 0 => EstadoComparendos::AcuerdoPago,
                        default => EstadoComparendos::SinComparendos,
                    },
                    'comparendos_cantidad' => $resumen['comparendos']['pendientes'] + $resumen['comparendos']['acuerdos'],
                    'comparendos_consultado_el' => $ahora->toDateString(),
                ]);
            }

            if ($nuevos) {
                $this->avisar($empresa, $sujeto, 'Comparendos nuevos', count($nuevos).' comparendo(s) nuevo(s) por $ '
                    .number_format(collect($nuevos)->sum('valor'), 0, ',', '.').'.', 'transito.comparendo_nuevo', ['comparendos' => $nuevos]);
            }
        } elseif ($tipo === TipoConsultaTransito::ConductorLicencia) {
            $antes = (bool) ($resumen['licencia']['suspendida'] ?? false);
            $resumen['licencia'] = ($r->ok() ? Interpretar::licencia($r->datos) : ['estado' => 'SIN LICENCIA EN EL RUNT', 'suspendida' => false, 'categorias' => [], 'tiene_multas' => null])
                + ['consultado_at' => $ahora->toIso8601String()];
            if ($resumen['licencia']['suspendida'] && ! $antes) {
                $this->avisar($empresa, $sujeto, 'Licencia suspendida', 'El RUNT reporta la licencia de conducción SUSPENDIDA.'
                    .($empresa->transito_bloquear_licencia ? ' No se le pueden expedir FUEC.' : ''), 'transito.licencia_suspendida', ['licencia' => $resumen['licencia']]);
            }
        } else {
            $runt = ($r->ok() ? Interpretar::runt($r->datos) : ['soat_vence' => null, 'soat_estado' => null, 'rtm_vence' => null, 'rtm_vigente' => null, 'estado_vehiculo' => null])
                + ['consultado_at' => $ahora->toIso8601String()];
            $runt['diferencias'] = $this->diferenciasRunt($sujeto, $runt);
            $resumen['runt'] = $runt;
            if ($runt['diferencias']) {
                $this->avisar($empresa, $sujeto, 'Diferencias con el RUNT', implode(' ', $runt['diferencias']), null);
            }
        }

        $sujeto->forceFill(['transito_resumen' => $resumen])->save();
    }

    /** @return list<array> los comparendos que no estaban antes */
    private function guardarComparendos(Empresa $empresa, Conductor|Vehiculo $sujeto, array $comparendos): array
    {
        $existentes = ComparendoTransito::query()->deEmpresa($empresa)
            ->where('consultable_type', $sujeto->getMorphClass())->where('consultable_id', $sujeto->getKey())
            ->get()->keyBy('numero');
        $nuevos = [];
        $vistos = [];

        foreach ($comparendos as $c) {
            $vistos[] = $c['numero'];
            $registro = $existentes->get($c['numero']) ?? new ComparendoTransito([
                'empresa_id' => $empresa->id,
                'consultable_type' => $sujeto->getMorphClass(),
                'consultable_id' => $sujeto->getKey(),
                'numero' => $c['numero'],
                'primera_vez_at' => now(),
            ]);
            if (! $registro->exists && $c['estado'] !== 'pagada') {
                $nuevos[] = $c;
            }
            $registro->forceFill([...Arr::only($c, ['numero', 'fecha', 'codigo', 'valor', 'estado', 'placa']), 'empresa_id' => $empresa->id, 'ultima_vez_at' => now(), 'ya_no_aparece_at' => null]);
            $registro->infraccion = $c['infraccion'] !== null ? Str::limit((string) $c['infraccion'], 500) : null;
            $registro->organismo = $c['organismo'] !== null ? Str::limit((string) $c['organismo'], 250) : null;
            $registro->tipo = $c['tipo'] !== null ? Str::limit((string) $c['tipo'], 20, '') : null;
            $registro->save();
        }

        // Los que ya no salen: pagados o depurados en el SIMIT.
        $existentes->except($vistos)->filter(fn ($c) => ! $c->ya_no_aparece_at)
            ->each(fn (ComparendoTransito $c) => $c->forceFill(['ya_no_aparece_at' => now()])->save());

        return $nuevos;
    }

    /** SOAT y técnico-mecánica del RUNT frente a los documentos registrados en la app. */
    private function diferenciasRunt(Vehiculo $vehiculo, array $runt): array
    {
        $diferencias = [];
        foreach ([[TipoDocumentoVehiculo::Soat, 'soat_vence', 'SOAT'], [TipoDocumentoVehiculo::Tecnomecanica, 'rtm_vence', 'técnico-mecánica']] as [$tipo, $campo, $nombre]) {
            $enApp = $vehiculo->documentoVigente($tipo)?->fecha_vencimiento?->toDateString();
            $enRunt = $runt[$campo] ?? null;
            if ($enRunt && $enApp && $enRunt !== $enApp) {
                $diferencias[] = "{$nombre}: en la app vence el ".Carbon::parse($enApp)->format('d/m/Y')
                    .' y en el RUNT el '.Carbon::parse($enRunt)->format('d/m/Y').'.';
            } elseif ($enApp && ! $enRunt) {
                $diferencias[] = "{$nombre}: registrado en la app pero el RUNT no reporta uno vigente.";
            }
        }

        return $diferencias;
    }

    private function avisar(Empresa $empresa, Conductor|Vehiculo $sujeto, string $titulo, string $detalle, ?string $evento, array $datos = []): void
    {
        $nombre = $sujeto instanceof Conductor ? $sujeto->nombre_completo : "Vehículo {$sujeto->placa}";
        rescue(fn () => Avisos::aQuienPueda($empresa, 'ver_consultas_transito',
            Notification::make()->warning()->title("{$titulo}: {$nombre}")->body($detalle)), report: false);

        if ($evento) {
            Webhooks::emitir($empresa, $evento, [
                'sujeto' => $sujeto instanceof Conductor ? 'conductor' : 'vehiculo',
                'nombre' => $nombre,
                'documento' => $sujeto instanceof Conductor ? $sujeto->documento : null,
                'placa' => $sujeto instanceof Vehiculo ? $sujeto->placa : null,
                'detalle' => $detalle,
                ...$datos,
            ]);
        }
    }
}
