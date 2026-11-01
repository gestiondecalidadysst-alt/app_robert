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
class Vehiculo extends Model
{
    use Auditable, PerteneceAEmpresa, SoftDeletes, TieneDocumentosConVencimiento;

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
        'empresa_id', 'placa', 'numero_interno', 'propietario_id', 'marca_id', 'linea_id', 'modelo',
        'clase_vehiculo_id', 'carroceria_id', 'combustible_id', 'tipo_servicio_id', 'color', 'cilindraje',
        'capacidad_pasajeros', 'capacidad_kg', 'puertas', 'numero_motor', 'vin', 'numero_chasis',
        'fecha_matricula', 'organismo_transito_id', 'foto_path', 'ficha_tecnica', 'tipo_vinculacion',
        'empresa_convenio_id', 'fecha_vinculacion', 'observaciones',
    ];

    protected $attributes = [
        'estado' => 'operando',
    ];

    protected function casts(): array
    {
        return [
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
}
