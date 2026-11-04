<?php

namespace App\Models;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoDocumentoVehiculo;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\Concerns\TieneDocumentosConVencimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vehículo de la flota de una empresa.
 *
 * El estado (operando, suspendido, taller, desvinculado) no se edita en el formulario:
 * se cambia con App\Actions\Flota\CambiarEstadoVehiculo, que deja la novedad en el historial.
 */
class Vehiculo extends Model implements \App\Services\Rndc\RegistrableRndc
{
    use Auditable, PerteneceAEmpresa, \App\Models\Concerns\SeRegistraEnRndc, SoftDeletes, TieneDocumentosConVencimiento;

    /** Campos de la ficha técnica (columna JSON ficha_tecnica). */
    public const FICHA_TECNICA = [
        'potencia_hp' => 'Potencia (HP)',
        'numero_ejes' => 'Número de ejes',
        'puertas_electricas' => 'Puertas eléctricas',
        'sistema_direccion' => 'Sistema de dirección',
        'frenos_delanteros' => 'Frenos delanteros',
        'frenos_traseros' => 'Frenos traseros',
        'llantas_delanteras' => 'Llantas delanteras',
        'llantas_traseras' => 'Llantas traseras',
        'suspension_delantera' => 'Suspensión delantera',
        'suspension_trasera' => 'Suspensión trasera',
    ];

    protected $table = 'vehiculos';

    protected $fillable = [
        'transito_auto',
        'empresa_id', 'placa', 'numero_interno', 'propietario_id', 'marca_id', 'linea_id', 'modelo',
        'clase_vehiculo_id', 'carroceria_id', 'combustible_id', 'tipo_servicio_id', 'color', 'cilindraje',
        'capacidad_pasajeros', 'capacidad_kg', 'puertas', 'numero_motor', 'vin', 'numero_chasis',
        'fecha_matricula', 'organismo_transito_id', 'foto_path', 'ficha_tecnica', 'tipo_vinculacion',
        'empresa_convenio_id', 'fecha_vinculacion', 'observaciones',
        'rndc_configuracion', 'numero_ejes', 'rndc_marca', 'rndc_linea', 'rndc_color', 'rndc_carroceria', 'rndc_combustible',
        'peso_vacio', 'unidad_capacidad', 'ano_repotenciacion', 'soat_nit_aseguradora', 'tenedor_id',
    ];

    protected $attributes = [
        'estado' => 'operando',
    ];

    protected function casts(): array
    {
        return [
            'transito_auto' => 'boolean',
            'transito_resumen' => 'array',
            'plan_personalizado' => 'boolean',
            'estado' => EstadoVehiculo::class,
            'estado_desde' => 'date',
            'fecha_matricula' => 'date',
            'fecha_vinculacion' => 'date',
            'ficha_tecnica' => 'array',
            'modelo' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Vehiculo $vehiculo): void {
            $vehiculo->placa = self::normalizarPlaca($vehiculo->placa);
            $vehiculo->numero_interno = filled($vehiculo->numero_interno) ? trim($vehiculo->numero_interno) : null;
        });

        static::creating(function (Vehiculo $vehiculo): void {
            $vehiculo->estado_desde ??= $vehiculo->fecha_vinculacion ?? now()->toDateString();
        });
    }

    /** "abc-123" -> "ABC123". */
    public static function normalizarPlaca(?string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $placa));
    }

    // Relaciones

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class)->withTrashed();
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function linea(): BelongsTo
    {
        return $this->belongsTo(Linea::class);
    }

    public function claseVehiculo(): BelongsTo
    {
        return $this->belongsTo(ClaseVehiculo::class);
    }

    public function carroceria(): BelongsTo
    {
        return $this->belongsTo(Carroceria::class);
    }

    public function combustible(): BelongsTo
    {
        return $this->belongsTo(Combustible::class);
    }

    public function tipoServicio(): BelongsTo
    {
        return $this->belongsTo(TipoServicio::class);
    }

    public function organismoTransito(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'organismo_transito_id');
    }

    public function empresaConvenio(): BelongsTo
    {
        return $this->belongsTo(EmpresaConvenio::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoVehiculo::class);
    }

    public function novedades(): HasMany
    {
        return $this->hasMany(NovedadVehiculo::class);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionVehiculo::class);
    }

    public function inspecciones(): HasMany
    {
        return $this->hasMany(Inspeccion::class);
    }

    public function plantillaMantenimiento(): BelongsTo
    {
        return $this->belongsTo(PlantillaMantenimiento::class);
    }

    /** Rutinas del plan personalizado del vehículo. */
    public function rutinasPropias(): HasMany
    {
        return $this->hasMany(RutinaMantenimiento::class)->orderBy('orden')->orderBy('id');
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class);
    }

    public function planesAccion(): HasMany
    {
        return $this->hasMany(PlanAccion::class);
    }

    /**
     * Kilometraje actual: el mayor reportado en preoperacionales, inspecciones o mantenimientos.
     * Null si no hay ninguno.
     */
    public function kilometrajeActual(): ?int
    {
        $inspecciones = Inspeccion::query()->deEmpresa($this->empresa_id)->where('vehiculo_id', $this->id)->max('kilometraje');
        $mantenimientos = Mantenimiento::query()->deEmpresa($this->empresa_id)->where('vehiculo_id', $this->id)->vigentes()->max('kilometraje');

        $km = max((int) $inspecciones, (int) $mantenimientos);

        return $km > 0 ? $km : null;
    }

    /** ¿Tiene un plan de acción de prioridad alta pendiente? (no puede quedar APTO hasta cerrarlo) */
    public function planAltaPendiente(): ?PlanAccion
    {
        return PlanAccion::query()->deEmpresa($this->empresa_id)->where('vehiculo_id', $this->id)
            ->pendientes()->where('prioridad', \App\Enums\PrioridadPlan::Alta->value)->oldest('id')->first();
    }

    /** Último preoperacional del día (el que cuenta para operar y para ver el extracto). */
    public function preoperacionalDelDia(?\DateTimeInterface $fecha = null): ?Inspeccion
    {
        return Inspeccion::query()->deEmpresa($this->empresa_id)
            ->where('vehiculo_id', $this->id)
            ->preoperacionales()
            ->delDia($fecha)
            ->latest('id')
            ->first();
    }

    public function asignacionesVigentes(): HasMany
    {
        return $this->asignaciones()->whereNull('fecha_hasta');
    }

    // Documentos (ver Concerns\TieneDocumentosConVencimiento)

    public static function tiposDocumento(): string
    {
        return TipoDocumentoVehiculo::class;
    }

    /**
     * Motivos por los que el vehículo no puede prestar servicio en una fecha (vacío = puede operar).
     * El módulo de Operación (FUEC) usa esto antes de expedir un extracto.
     *
     * @return list<string>
     */
    public function bloqueosOperacion(?\DateTimeInterface $fecha = null): array
    {
        $bloqueos = [];

        if ($this->estado !== EstadoVehiculo::Operando) {
            $bloqueos[] = 'El vehículo está ' . mb_strtolower($this->estado->getLabel()) . '.';
        }

        return [...$bloqueos, ...$this->bloqueosDocumentales($fecha)];
    }

    public function puedeOperar(?\DateTimeInterface $fecha = null): bool
    {
        return $this->bloqueosOperacion($fecha) === [];
    }

    // Presentación

    public function getDescripcionAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->marca?->nombre, $this->linea?->nombre, $this->modelo])));
    }

    // Scopes

    public function scopeEnFlota(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), '!=', EstadoVehiculo::Desvinculado->value);
    }

    public function scopeEnEstado(Builder $query, EstadoVehiculo $estado): Builder
    {
        return $query->where($query->qualifyColumn('estado'), $estado->value);
    }

    // Consultas de tránsito

    public function comparendosTransito(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ComparendoTransito::class, 'consultable');
    }

    public function consultasTransito(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ConsultaTransito::class, 'consultable');
    }

    // RNDC (módulo de carga): se registra como vehículo, proceso 12.

    public function tenedor(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'tenedor_id')->withoutGlobalScopes();
    }

    public function esDeCarga(): bool
    {
        return $this->rndc_configuracion !== null;
    }

    public function procesoRndc(): int
    {
        return 12;
    }

    public function etiquetaRndc(): string
    {
        return 'Vehículo ' . $this->placa . ' · ' . (\App\Support\Carga\CatalogoRndc::CONFIGURACIONES_VEHICULO[$this->rndc_configuracion] ?? 'sin configuración de carga');
    }

    public function variablesRndc(Empresa $empresa): array
    {
        $c = \App\Support\Carga\CatalogoRndc::class;
        $propietario = $this->propietario()->withoutGlobalScopes()->with('tipoIdentificacion')->first();
        $tenedor = $this->tenedor()->with('tipoIdentificacion')->first() ?? $propietario;
        $soat = $this->documentoVigente(TipoDocumentoVehiculo::Soat);
        $soat?->loadMissing('aseguradora');
        $config = $this->rndc_configuracion;

        return array_filter([
            'NUMNITEMPRESATRANSPORTE' => $c::digitos($empresa->nit),
            'NUMPLACA' => \Illuminate\Support\Str::upper($this->placa),
            'CODCONFIGURACIONUNIDADCARGA' => $config,
            'CODMARCAVEHICULOCARGA' => $this->rndc_marca,
            'CODLINEAVEHICULOCARGA' => $this->rndc_linea,
            'NUMEJES' => in_array($config, $c::CON_EJES, true) ? $this->numero_ejes : null,
            'ANOFABRICACIONVEHICULOCARGA' => $this->modelo,
            'ANOREPOTENCIACION' => $this->ano_repotenciacion,
            'CODCOLORVEHICULOCARGA' => $this->rndc_color,
            'PESOVEHICULOVACIO' => $this->peso_vacio,
            'CAPACIDADUNIDADCARGA' => in_array($config, $c::CON_CAPACIDAD, true) ? $this->capacidad_kg : null,
            'UNIDADMEDIDACAPACIDAD' => $this->unidad_capacidad ?: 1,
            'CODTIPOCARROCERIA' => in_array($config, $c::TRACTOCAMIONES, true) ? '0' : $this->rndc_carroceria,
            'NUMCHASIS' => $this->numero_chasis ?: $this->vin,
            'CODTIPOCOMBUSTIBLE' => $this->rndc_combustible,
            'NUMSEGUROSOAT' => $soat?->numero,
            'FECHAVENCIMIENTOSOAT' => $soat?->fecha_vencimiento?->format('d/m/Y'),
            'NUMNITASEGURADORASOAT' => $c::digitos($this->soat_nit_aseguradora ?: $soat?->aseguradora?->nit) ?: null,
            'CODTIPOIDPROPIETARIO' => $c::tipoId($propietario?->tipoIdentificacion?->codigo),
            'NUMIDPROPIETARIO' => $c::digitos($propietario?->documento),
            'CODTIPOIDTENEDOR' => $c::tipoId($tenedor?->tipoIdentificacion?->codigo),
            'NUMIDTENEDOR' => $c::digitos($tenedor?->documento),
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function validarRndc(): array
    {
        $c = \App\Support\Carga\CatalogoRndc::class;
        $propietario = $this->propietario()->withoutGlobalScopes()->with('tipoIdentificacion')->first();
        $tenedor = $this->tenedor()->with('tipoIdentificacion')->first() ?? $propietario;
        $e = \App\Services\Rndc\ValidarVehiculo::comunes($this->placa, $this->rndc_configuracion, $this->rndc_marca, (int) $this->modelo,
            $this->peso_vacio, $this->capacidad_kg, $this->numero_ejes, $propietario, $tenedor);
        if ($this->rndc_configuracion && ! isset($c::CONFIGURACIONES_VEHICULO[$this->rndc_configuracion])) {
            $e[] = 'La configuración no es de camión rígido o tractocamión.';
        }
        if (! preg_match('/^[A-Z]{3}\d{3}$/', \Illuminate\Support\Str::upper((string) $this->placa))) {
            $e[] = 'La placa de un camión o tractocamión es de 3 letras y 3 números.';
        }
        if (blank($this->rndc_linea)) {
            $e[] = 'Falta el código RNDC de la línea.';
        }
        if (blank($this->rndc_color)) {
            $e[] = 'Falta el código RNDC del color.';
        }
        if (! $this->rndc_combustible) {
            $e[] = 'Falta el tipo de combustible.';
        }
        if (blank($this->numero_chasis ?: $this->vin)) {
            $e[] = 'Falta el número de chasis (serie).';
        }
        $soat = $this->documentoVigente(TipoDocumentoVehiculo::Soat);
        if (! $soat || blank($soat->numero) || ! $soat->fecha_vencimiento) {
            $e[] = 'Falta el SOAT vigente con número y fecha de vencimiento (documentos del vehículo).';
        }
        if (blank($this->soat_nit_aseguradora) && blank($soat?->aseguradora?->nit)) {
            $e[] = 'Falta el NIT de la aseguradora del SOAT.';
        }
        if (! in_array($this->rndc_configuracion, $c::TRACTOCAMIONES, true) && blank($this->rndc_carroceria)) {
            $e[] = 'Falta el código RNDC del tipo de carrocería.';
        }

        return $e;
    }
}
