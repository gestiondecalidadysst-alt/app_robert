<?php

namespace App\Models;

use App\Enums\EstadoComparendos;
use App\Enums\EstadoConductor;
use App\Enums\TipoDocumentoConductor;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\Concerns\TieneDocumentosConVencimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Conductor (operador) de la empresa.
 *
 * El estado (activo, suspendido, retirado) no se edita en el formulario: se cambia con
 * App\Actions\Conductores\CambiarEstadoConductor, que deja la novedad en el historial.
 */
class Conductor extends Model
{
    use Auditable, PerteneceAEmpresa, SoftDeletes, TieneDocumentosConVencimiento;

    public const GENEROS = ['F' => 'Femenino', 'M' => 'Masculino', 'O' => 'Otro'];

    public const GRUPOS_SANGUINEOS = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

    protected $table = 'conductores';

    protected $fillable = [
        'empresa_id', 'tipo_identificacion_id', 'documento', 'nombres', 'apellidos', 'fecha_nacimiento', 'genero',
        'grupo_sanguineo', 'telefono', 'email', 'direccion', 'municipio_id', 'foto_path', 'nivel_academico_id',
        'oficina_id', 'fecha_ingreso', 'eps_id', 'arl_id', 'fondo_pensiones', 'contacto_emergencia',
        'telefono_emergencia', 'comparendos_estado', 'comparendos_cantidad', 'comparendos_consultado_el',
        'observaciones',
    ];

    protected $attributes = [
        'estado' => 'activo',
        'comparendos_cantidad' => 0,
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoConductor::class,
            'estado_desde' => 'date',
            'comparendos_estado' => EstadoComparendos::class,
            'comparendos_consultado_el' => 'date',
            'comparendos_cantidad' => 'integer',
            'fecha_nacimiento' => 'date',
            'fecha_ingreso' => 'date',
            'firma_actualizada_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Conductor $conductor): void {
            $conductor->documento = preg_replace('/[^0-9A-Za-z]/', '', (string) $conductor->documento);
        });

        static::creating(function (Conductor $conductor): void {
            $conductor->estado_desde ??= $conductor->fecha_ingreso ?? now()->toDateString();
        });
    }

    // Relaciones

    public function tipoIdentificacion(): BelongsTo
    {
        return $this->belongsTo(TipoIdentificacion::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function nivelAcademico(): BelongsTo
    {
        return $this->belongsTo(NivelAcademico::class);
    }

    public function oficina(): BelongsTo
    {
        return $this->belongsTo(Oficina::class);
    }

    public function eps(): BelongsTo
    {
        return $this->belongsTo(Eps::class);
    }

    public function arl(): BelongsTo
    {
        return $this->belongsTo(Arl::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoConductor::class);
    }

    public function novedades(): HasMany
    {
        return $this->hasMany(NovedadConductor::class);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionVehiculo::class);
    }

    public function inspecciones(): HasMany
    {
        return $this->hasMany(Inspeccion::class);
    }

    /** Firma registrada una sola vez; se estampa en cada preoperacional (ver RegistrarInspeccion). */
    public function tieneFirma(): bool
    {
        // También revisa el archivo: si se perdió (p. ej. un despliegue sin volumen en storage/app),
        // el portal vuelve a pedirla en vez de registrar preoperacionales sin firma.
        return filled($this->firma_path)
            && \Illuminate\Support\Facades\Storage::disk(\App\Actions\Pesv\GuardarFirmaConductor::DISCO)->exists($this->firma_path);
    }

    public function asignacionesVigentes(): HasMany
    {
        return $this->asignaciones()->whereNull('fecha_hasta');
    }

    // Documentos (ver Concerns\TieneDocumentosConVencimiento)

    public static function tiposDocumento(): string
    {
        return TipoDocumentoConductor::class;
    }

    /** Licencia vigente (con su categoría). */
    public function licencia(): ?DocumentoConductor
    {
        return $this->documentoVigente(TipoDocumentoConductor::Licencia);
    }

    /**
     * Motivos por los que el conductor no puede prestar servicio en una fecha (vacío = puede).
     * El módulo de Operación (FUEC) usa esto antes de expedir un extracto.
     *
     * @return list<string>
     */
    public function bloqueosOperacion(?\DateTimeInterface $fecha = null): array
    {
        $bloqueos = [];

        if ($this->estado !== EstadoConductor::Activo) {
            $bloqueos[] = 'El conductor está ' . mb_strtolower($this->estado->getLabel()) . '.';
        }

        $bloqueos = [...$bloqueos, ...$this->bloqueosDocumentales($fecha)];

        $categoria = $this->licencia()?->categoriaLicencia;
        if ($categoria && ! $categoria->esServicioPublico()) {
            $bloqueos[] = "La licencia es categoría {$categoria->codigo} (servicio particular); el transporte especial exige categoría C.";
        }

        return $bloqueos;
    }

    public function puedeOperar(?\DateTimeInterface $fecha = null): bool
    {
        return $this->bloqueosOperacion($fecha) === [];
    }

    /** Tiene comparendos sin resolver según la última consulta (no bloquea; es un aviso). */
    public function tieneComparendos(): bool
    {
        return $this->comparendos_estado !== null && $this->comparendos_estado !== EstadoComparendos::SinComparendos;
    }

    // Presentación

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombres . ' ' . $this->apellidos);
    }

    // Scopes

    public function scopeVinculados(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), '!=', EstadoConductor::Retirado->value);
    }
}
