<?php

namespace App\Actions\Operacion;

use App\Enums\ObjetoContrato;
use App\Enums\TipoDocumentoVehiculo;
use App\Models\Conductor;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\EmpresaConvenio;
use App\Models\Fuec;
use App\Models\Municipio;
use App\Models\Vehiculo;
use App\Support\DireccionesTerritoriales;
use App\Support\Nit;
use App\Support\Operacion\Horario;
use App\Support\Push\NotificarConductor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Expide un FUEC (Resolución 6652 de 2019). Es la única forma de crear uno.
 *
 * Valida, en el servidor y con mensajes por campo:
 * - La empresa tiene los datos de habilitación que exige el número (art. 4).
 * - El contrato no está anulado y la vigencia del FUEC está dentro de la del contrato (art. 15).
 * - El vehículo y entre 1 y 3 conductores distintos pueden operar al iniciar (bloqueosOperacion).
 * - Ningún documento obligatorio vence antes de terminar el FUEC: si alguno vence, se indica la
 *   fecha máxima posible (acortar la vigencia o renovar el documento).
 *
 * Guarda en `datos` una copia de la empresa, el contrato, el vehículo y los conductores.
 */
class ExpedirFuec
{
    public const MAX_CONDUCTORES = 3;

    /** Fecha máxima calculada en la última validación (null si ningún documento limita). */
    private ?Carbon $fechaMaxima = null;

    /**
     * @param  array{contrato_id:int, vehiculo_id:int, conductores:list<int>, fecha_inicio:string, fecha_fin:string,
     *               origen_municipio_id:int, destino_municipio_id:int, recorrido?:?string, empresa_convenio_id?:?int,
     *               servicio_id?:?int}  $datos  servicio_id: servicio del planeador desde el que se genera
     */
    public function __invoke(Empresa $empresa, array $datos): Fuec
    {
        $errores = $this->validar($empresa, $datos);

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        $fuec = DB::transaction(function () use ($empresa, $datos): Fuec {
            /** @var Contrato $contrato */
            $contrato = Contrato::query()->deEmpresa($empresa)->whereKey($datos['contrato_id'])->lockForUpdate()->firstOrFail();
            $vehiculo = Vehiculo::query()->deEmpresa($empresa)->with(['marca', 'linea', 'claseVehiculo', 'documentos'])->findOrFail($datos['vehiculo_id']);
            $conductores = $this->conductores($empresa, $datos['conductores']);
            $inicio = Carbon::parse($datos['fecha_inicio']);

            $consecutivo = (int) $contrato->fuecs()->max('consecutivo') + 1;

            $fuec = new Fuec([
                'contrato_id' => $contrato->id,
                'consecutivo' => $consecutivo,
                'numero' => self::numero($empresa, $inicio->year, $contrato->numero, $consecutivo),
                'vehiculo_id' => $vehiculo->id,
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => Carbon::parse($datos['fecha_fin'])->toDateString(),
                'hora_inicio' => self::hora($datos['hora_inicio'] ?? null),
                'hora_fin' => self::hora($datos['hora_fin'] ?? null),
                'origen_municipio_id' => $datos['origen_municipio_id'],
                'destino_municipio_id' => $datos['destino_municipio_id'],
                'recorrido' => $datos['recorrido'] ?? null,
                'empresa_convenio_id' => $datos['empresa_convenio_id'] ?? null,
                'codigo_verificacion' => Str::lower(Str::random(24)),
                'oficina_id' => $datos['oficina_id'] ?? $contrato->oficina_id,
                'user_id' => auth()->id(),
            ]);
            $fuec->empresa_id = $empresa->id;
            $fuec->datos = $this->copiaDeDatos($empresa, $contrato, $vehiculo, $conductores, $fuec);
            $fuec->save();

            $fuec->conductores()->attach(
                $conductores->values()->mapWithKeys(fn (Conductor $c, int $i) => [$c->id => ['orden' => $i + 1]])->all()
            );

            // Turismo y Grupo específico llevan listado de pasajeros (autorregistro por enlace).
            if (in_array($contrato->objeto, [ObjetoContrato::Turismo, ObjetoContrato::GrupoEspecifico], true)) {
                $fuec->abrirListadoPasajeros();
            }

            // Todo extracto aparece en el planeador: enlaza el servicio planificado o crea uno.
            app(PlanificarServicio::class)->desdeFuec($fuec, filled($datos['servicio_id'] ?? null) ? (int) $datos['servicio_id'] : null);

            return $fuec;
        });

        // Avisa por push a los conductores del nuevo servicio (fuera de la transacción).
        NotificarConductor::nuevoExtracto($fuec);

        return $fuec;
    }

    /**
     * Revisa los datos sin expedir. Devuelve los errores por campo (vacío = se puede expedir).
     * La pantalla lo usa para mostrar los bloqueos antes de enviar.
     *
     * @return array<string, list<string>>
     */
    public function validar(Empresa $empresa, array $datos): array
    {
        $errores = [];
        $this->fechaMaxima = null;
        $agregar = function (string $campo, string $mensaje) use (&$errores): void {
            $errores[$campo][] = $mensaje;
        };

        if ($faltan = $empresa->faltantesHabilitacion()) {
            $agregar('contrato_id', 'Complete en «Mi empresa»: '.implode(', ', $faltan).'. Son parte del número del FUEC.');
        } elseif (! array_key_exists((string) $empresa->codigo_territorial, DireccionesTerritoriales::CODIGOS)) {
            $agregar('contrato_id', "El código territorial «{$empresa->codigo_territorial}» no es de una Dirección Territorial del Ministerio. Corríjalo en «Mi empresa».");
        }

        $inicio = filled($datos['fecha_inicio'] ?? null) ? Carbon::parse($datos['fecha_inicio'])->startOfDay() : null;
        $fin = filled($datos['fecha_fin'] ?? null) ? Carbon::parse($datos['fecha_fin'])->startOfDay() : null;

        if (! $inicio) {
            $agregar('fecha_inicio', 'Indique la fecha de inicio.');
        } elseif ($inicio->lt(today())) {
            $agregar('fecha_inicio', 'El FUEC no puede iniciar en una fecha pasada.');
        }

        if (! $fin) {
            $agregar('fecha_fin', 'Indique la fecha de terminación.');
        } elseif ($inicio && $fin->lt($inicio)) {
            $agregar('fecha_fin', 'La terminación no puede ser anterior al inicio.');
        }

        $horaInicio = self::hora($datos['hora_inicio'] ?? null);
        $horaFin = self::hora($datos['hora_fin'] ?? null);
        if ($horaInicio !== null && $horaInicio === $horaFin) {
            $agregar('hora_fin', 'La hora final debe ser distinta de la inicial (déjelas vacías si el servicio es todo el día).');
        }

        // Contrato
        $contrato = filled($datos['contrato_id'] ?? null)
            ? Contrato::query()->deEmpresa($empresa)->find($datos['contrato_id'])
            : null;

        if (! $contrato) {
            $agregar('contrato_id', 'Elija el contrato.');
        } elseif ($contrato->anulado()) {
            $agregar('contrato_id', "El contrato {$contrato->numero_formateado} está anulado.");
        } elseif ($inicio && $fin && ($inicio->lt($contrato->fecha_inicio) || $fin->gt($contrato->fecha_fin))) {
            $agregar('fecha_fin', "La vigencia del FUEC debe estar dentro de la del contrato ({$contrato->vigencia}).");
        }

        // Regla opcional por empresa: el contrato debe estar firmado por el cliente.
        if ($contrato && ! $contrato->anulado() && $empresa->exigir_contrato_firmado_fuec && ! $contrato->firmado()) {
            $agregar('contrato_id', "El contrato {$contrato->numero_formateado} aún no está firmado por el cliente. Debe estar firmado para poder expedir el FUEC.");
        }

        // Vehículo
        $vehiculo = filled($datos['vehiculo_id'] ?? null)
            ? Vehiculo::query()->deEmpresa($empresa)->with('documentos')->find($datos['vehiculo_id'])
            : null;

        if (! $vehiculo) {
            $agregar('vehiculo_id', 'Elija el vehículo.');
        } else {
            foreach ($vehiculo->bloqueosOperacion($inicio) as $bloqueo) {
                $agregar('vehiculo_id', "{$vehiculo->placa} · {$bloqueo}");
            }
        }

        $vencenAntes = [];

        // Conductores
        $ids = array_values(array_filter((array) ($datos['conductores'] ?? []), 'filled'));

        if ($ids === []) {
            $agregar('conductores', 'Elija al menos un conductor.');
        } elseif (count($ids) > self::MAX_CONDUCTORES) {
            $agregar('conductores', 'Máximo '.self::MAX_CONDUCTORES.' conductores por FUEC.');
        } elseif (count(array_unique($ids)) !== count($ids)) {
            $agregar('conductores', 'Hay un conductor repetido.');
        } else {
            $conductores = $this->conductores($empresa, $ids);

            if ($conductores->count() !== count($ids)) {
                $agregar('conductores', 'Algún conductor no existe en esta empresa.');
            }

            foreach ($conductores as $conductor) {
                foreach ($conductor->bloqueosOperacion($inicio) as $bloqueo) {
                    $agregar('conductores', "{$conductor->nombre_completo} · {$bloqueo}");
                }
            }
        }

        // Cruce de horarios: el vehículo y los conductores no pueden estar en otro servicio al mismo tiempo.
        if ($inicio && $fin && ! $fin->lt($inicio) && ! isset($errores['hora_fin'])) {
            $horario = new Horario($inicio, $fin, $horaInicio, $horaFin);

            foreach ($this->cruces($empresa, $horario, $vehiculo?->id, $ids) as [$campo, $mensaje]) {
                $agregar($campo, $mensaje);
            }
        }

        // Documentos vigentes al iniciar que vencen antes de terminar el FUEC.
        if ($inicio && $fin && $fin->gt($inicio)) {
            $duenos = collect([$vehiculo ? [$vehiculo->placa, $vehiculo] : null])
                ->merge(($conductores ?? collect())->map(fn (Conductor $c) => [$c->nombre_completo, $c]))
                ->filter();

            foreach ($duenos as [$nombre, $dueno]) {
                foreach ($dueno->vencenAntesDe($fin) as $documento) {
                    if ($documento->fecha_vencimiento->gte($inicio)) {
                        $vencenAntes[] = [$documento->fecha_vencimiento, "{$nombre} · {$documento->tipo->getLabel()} vence el {$documento->fecha_vencimiento->format('d/m/Y')}"];
                    }
                }
            }

            if ($vencenAntes !== []) {
                $limite = $this->fechaMaxima = collect($vencenAntes)->min(fn ($v) => $v[0])->copy();
                $agregar('fecha_fin', 'Con esta vigencia vencen documentos: '.collect($vencenAntes)->pluck(1)->implode('; ')
                    .". La fecha de vencimiento máxima posible es {$limite->format('d/m/Y')}; acórtela o renueve antes los documentos.");
            }
        }

        // Origen, destino y convenio
        foreach (['origen_municipio_id' => 'origen', 'destino_municipio_id' => 'destino'] as $campo => $nombre) {
            if (blank($datos[$campo] ?? null) || ! Municipio::query()->whereKey($datos[$campo])->exists()) {
                $agregar($campo, "Indique el municipio de {$nombre}.");
            }
        }

        if (filled($datos['empresa_convenio_id'] ?? null)
            && ! EmpresaConvenio::query()->deEmpresa($empresa)->whereKey($datos['empresa_convenio_id'])->exists()) {
            $agregar('empresa_convenio_id', 'El convenio no pertenece a esta empresa.');
        }

        return $errores;
    }

    /**
     * Fecha de vencimiento máxima posible para el FUEC: el primer vencimiento, dentro de la vigencia
     * pedida, de los documentos obligatorios del vehículo y los conductores. Null si ninguno limita.
     */
    public function fechaMaxima(Empresa $empresa, array $datos): ?Carbon
    {
        $this->validar($empresa, $datos);

        return $this->fechaMaxima;
    }

    /**
     * Número de 21 dígitos (art. 4 Res. 6652/2019):
     * territorial (3) + resolución de habilitación (4) + año de habilitación (2) + año de expedición (4)
     * + consecutivo del contrato (4) + consecutivo del extracto en el contrato (4).
     * Los bloques numéricos más largos toman sus últimos dígitos; los más cortos se completan con ceros.
     */
    public static function numero(Empresa $empresa, int $anioExpedicion, int $numeroContrato, int $consecutivo): string
    {
        $bloque = fn ($valor, int $largo) => str_pad(substr(preg_replace('/\D/', '', (string) $valor), -$largo), $largo, '0', STR_PAD_LEFT);

        return $bloque($empresa->codigo_territorial, 3)
            .$bloque($empresa->resolucion_habilitacion, 4)
            .$bloque($empresa->anio_habilitacion, 2)
            .$bloque($anioExpedicion, 4)
            .$bloque($numeroContrato, 4)
            .$bloque($consecutivo, 4);
    }

    /**
     * FUEC vigentes (no anulados) del mismo vehículo o de los mismos conductores cuyo horario se cruza.
     *
     * @return list<array{0:string,1:string}> [campo, mensaje]
     */
    private function cruces(Empresa $empresa, Horario $horario, ?int $vehiculoId, array $conductorIds): array
    {
        $conductorIds = array_map('intval', $conductorIds);
        if (! $vehiculoId && $conductorIds === []) {
            return [];
        }

        $candidatos = Fuec::query()->deEmpresa($empresa)->noAnulados()
            ->whereDate('fecha_inicio', '<=', $horario->fechaFin->copy()->addDay())
            ->whereDate('fecha_fin', '>=', $horario->fechaInicio->copy()->subDay())
            ->where(fn ($q) => $q->when($vehiculoId, fn ($q) => $q->where('vehiculo_id', $vehiculoId))
                ->orWhereHas('conductores', fn ($c) => $c->whereIn('conductores.id', $conductorIds ?: [0])))
            ->with(['conductores', 'vehiculo'])
            ->orderBy('fecha_inicio')
            ->get()
            ->filter(fn (Fuec $f) => $f->horario()->seCruzaCon($horario));

        $cruces = [];
        foreach ($candidatos as $otro) {
            $detalle = "FUEC {$otro->numero_formateado} ({$otro->horario()->texto()})";

            if ($vehiculoId && $otro->vehiculo_id === $vehiculoId) {
                $cruces[] = ['vehiculo_id', "{$otro->placa} ya está asignado al {$detalle}. Elija otro vehículo u otro horario."];
            }
            foreach ($otro->conductores->whereIn('id', $conductorIds) as $conductor) {
                $cruces[] = ['conductores', "{$conductor->nombre_completo} ya está asignado al {$detalle} con el vehículo {$otro->placa}."];
            }
        }

        return $cruces;
    }

    /** "06:00" o "06:00:00" → "06:00:00"; vacío → null. */
    private static function hora(mixed $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        return preg_match('/^(\d{1,2}):(\d{2})/', (string) $valor, $m)
            ? sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2])
            : null;
    }

    private function conductores(Empresa $empresa, array $ids)
    {
        $encontrados = Conductor::query()->deEmpresa($empresa)
            ->with(['documentos.categoriaLicencia', 'tipoIdentificacion'])
            ->whereKey($ids)
            ->get()
            ->keyBy('id');

        // Respeta el orden elegido (conductor 1, 2, 3).
        return collect($ids)->map(fn ($id) => $encontrados->get((int) $id))->filter()->values();
    }

    public function copiaDeDatos(Empresa $empresa, Contrato $contrato, Vehiculo $vehiculo, $conductores, Fuec $fuec): array
    {
        $empresa->loadMissing('municipio.departamento');
        $contrato->loadMissing('cliente.tipoIdentificacion', 'cliente.municipio');
        $convenio = $fuec->empresa_convenio_id ? EmpresaConvenio::query()->deEmpresa($empresa)->find($fuec->empresa_convenio_id) : null;
        $tarjeta = $vehiculo->documentoVigente(TipoDocumentoVehiculo::TarjetaOperacion);

        return [
            'empresa' => [
                'razon_social' => $empresa->razon_social,
                'nit' => Nit::formatear($empresa->nit, $empresa->dv),
                'direccion' => $empresa->direccion,
                'telefono' => $empresa->telefono,
                'email' => $empresa->email,
                'municipio' => $empresa->municipio?->nombre_completo,
                'representante_legal' => $empresa->representante_legal,
                'representante_documento' => $empresa->representante_documento,
                'resolucion_habilitacion' => $empresa->resolucion_habilitacion,
                'anio_habilitacion' => $empresa->anio_habilitacion,
                'logo_path' => $empresa->logo_path,
                'firma_representante_path' => $empresa->firma_representante_path,
            ],
            'contrato' => [
                'numero' => $contrato->numero_formateado,
                'contratante' => $contrato->cliente?->nombre,
                'identificacion' => $contrato->cliente?->identificacion,
                'direccion' => $contrato->cliente?->direccion,
                'telefono' => $contrato->cliente?->telefono,
                'objeto' => $contrato->objeto->getLabel(),
                'descripcion_objeto' => $contrato->descripcion_objeto,
                'fecha_inicio' => $contrato->fecha_inicio->toDateString(),
                'fecha_fin' => $contrato->fecha_fin->toDateString(),
                'disposicion_total' => $contrato->disposicion_total,
                'zonas_disposicion' => $contrato->zonas_disposicion,
                'responsable' => array_filter([
                    'nombre' => $contrato->responsable_nombre,
                    'documento' => $contrato->responsable_documento,
                    'telefono' => $contrato->responsable_telefono,
                    'direccion' => $contrato->responsable_direccion,
                ]),
            ],
            'vehiculo' => [
                'placa' => $vehiculo->placa,
                'modelo' => $vehiculo->modelo,
                'marca' => $vehiculo->marca?->nombre,
                'linea' => $vehiculo->linea?->nombre,
                'clase' => $vehiculo->claseVehiculo?->nombre,
                'numero_interno' => $vehiculo->numero_interno,
                'tarjeta_operacion' => $tarjeta?->numero,
                'tarjeta_operacion_vence' => $tarjeta?->fecha_vencimiento?->toDateString(),
            ],
            'conductores' => $conductores->values()->map(function (Conductor $c, int $i) {
                $licencia = $c->licencia();

                return [
                    'orden' => $i + 1,
                    'nombre' => $c->nombre_completo,
                    'tipo_documento' => $c->tipoIdentificacion?->codigo ?? 'CC',
                    'documento' => $c->documento,
                    'licencia' => $licencia?->numero,
                    'categoria' => $licencia?->categoriaLicencia?->codigo,
                    'licencia_vence' => $licencia?->fecha_vencimiento?->toDateString(),
                ];
            })->all(),
            'origen' => Municipio::with('departamento')->find($fuec->origen_municipio_id)?->nombre_completo,
            'destino' => Municipio::with('departamento')->find($fuec->destino_municipio_id)?->nombre_completo,
            'convenio' => $convenio ? [
                'razon_social' => $convenio->razon_social,
                'nit' => Nit::formatear($convenio->nit, $convenio->dv),
            ] : null,
        ];
    }
}
