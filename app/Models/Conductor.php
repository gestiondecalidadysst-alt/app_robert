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
class Conductor extends Model implements \App\Services\Rndc\RegistrableRndc
{
    use Auditable, PerteneceAEmpresa, \App\Models\Concerns\SeRegistraEnRndc, SoftDeletes, TieneDocumentosConVencimiento;

    public const GENEROS = ['F' => 'Femenino', 'M' => 'Masculino', 'O' => 'Otro'];

    public const GRUPOS_SANGUINEOS = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

    protected $table = 'conductores';

    protected $fillable = [
        'transito_auto',
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
            'transito_auto' => 'boolean',
            'transito_resumen' => 'array',
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

        $bloqueos = [...$bloqueos, ...$this->bloqueosTransito()];

        $categoria = $this->licencia()?->categoriaLicencia;
        if ($categoria && ! $categoria->esServicioPublico()) {
            $bloqueos[] = "La licencia es categoría {$categoria->codigo} (servicio particular); el transporte especial exige categoría C.";
        }

        return $bloqueos;
    }

    /**
     * Bloqueos por la última consulta de tránsito, solo si la empresa los activó:
     * licencia suspendida en el RUNT y comparendos pendientes (desde el valor que defina).
     */
    public function bloqueosTransito(): array
    {
        $resumen = $this->transito_resumen ?? [];
        if ($resumen === []) {
            return [];
        }
        $empresa = $this->relationLoaded('empresa') ? $this->empresa : Empresa::query()->find($this->empresa_id);
        if (! $empresa?->transito_habilitado) {
            return [];
        }

        $bloqueos = [];
        $fecha = fn (?string $f) => $f ? \Illuminate\Support\Carbon::parse($f)->timezone(config('app.timezone'))->format('d/m/Y') : '';

        if ($empresa->transito_bloquear_licencia && ($resumen['licencia']['suspendida'] ?? false)) {
            $bloqueos[] = 'Licencia de conducción SUSPENDIDA según el RUNT (consulta del ' . $fecha($resumen['licencia']['consultado_at'] ?? null) . ').';
        }

        $comparendos = $resumen['comparendos'] ?? null;
        if ($empresa->transito_bloquear_comparendos && $comparendos && ($comparendos['pendientes'] ?? 0) > 0) {
            $deuda = (float) ($comparendos['deuda'] ?? 0);
            $desde = $empresa->transito_bloquear_desde !== null ? (float) $empresa->transito_bloquear_desde : 0;
            if ($deuda >= $desde) {
                $bloqueos[] = "Tiene {$comparendos['pendientes']} comparendo(s) pendiente(s) por $ " . number_format($deuda, 0, ',', '.')
                    . ' según el SIMIT (consulta del ' . $fecha($comparendos['consultado_at'] ?? null) . ').';
            }
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

    // Consultas de tránsito

    public function comparendosTransito(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ComparendoTransito::class, 'consultable');
    }

    public function consultasTransito(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ConsultaTransito::class, 'consultable');
    }

    // RNDC (módulo de carga): se registra como tercero, proceso 11.

    public function procesoRndc(): int
    {
        return 11;
    }

    public function etiquetaRndc(): string
    {
        return trim($this->nombres . ' ' . $this->apellidos) . ' · ' . ($this->tipoIdentificacion?->codigo ?? '') . ' ' . $this->documento;
    }

    /** Nombres y apellidos como los pide el RNDC (el primer apellido y el resto). */
    private function apellidosRndc(): array
    {
        $partes = preg_split('/\s+/', trim((string) $this->apellidos), 2) ?: [];

        return [$partes[0] ?? null, $partes[1] ?? null];
    }

    /** Conductor: con los datos de su licencia vigente (categoría, número y vencimiento). */
    public function variablesRndc(Empresa $empresa): array
    {
        $this->loadMissing(['tipoIdentificacion', 'municipio']);
        $c = \App\Support\Carga\CatalogoRndc::class;
        $licencia = $this->licencia();
        $licencia?->loadMissing('categoriaLicencia');
        $tel = $c::digitos($this->telefono);
        [$a1, $a2] = $this->apellidosRndc();

        return array_filter([
            'NUMNITEMPRESATRANSPORTE' => $c::digitos($empresa->nit),
            'CODTIPOIDTERCERO' => $c::tipoId($this->tipoIdentificacion?->codigo),
            'NUMIDTERCERO' => $c::digitos($this->documento),
            'NOMIDTERCERO' => $c::texto($this->nombres, 100),
            'PRIMERAPELLIDOIDTERCERO' => $c::texto($a1, 100),
            'SEGUNDOAPELLIDOIDTERCERO' => $c::texto($a2, 100),
            'NUMTELEFONOCONTACTO' => strlen($tel) <= 7 ? $tel : null,
            'NUMCELULARPERSONA' => strlen($tel) === 10 ? $tel : null,
            'NOMENCLATURADIRECCION' => $c::texto($this->direccion, 60),
            'CODMUNICIPIORNDC' => $c::municipio($this->municipio?->codigo_dane),
            'CODCATEGORIALICENCIACONDUCCION' => strtoupper((string) $licencia?->categoriaLicencia?->codigo),
            'NUMLICENCIACONDUCCION' => $c::digitos($licencia?->numero),
            'FECHAVENCIMIENTOLICENCIA' => $licencia?->fecha_vencimiento?->format('d/m/Y'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function validarRndc(): array
    {
        $c = \App\Support\Carga\CatalogoRndc::class;
        $licencia = $this->licencia();
        $licencia?->loadMissing('categoriaLicencia');
        $tel = $c::digitos($this->telefono);
        [$a1] = $this->apellidosRndc();

        return \App\Services\Rndc\ValidarTercero::errores($c::tipoId($this->tipoIdentificacion?->codigo), $this->documento, $this->nombres, $a1,
            strlen($tel) <= 7 ? $tel : null, strlen($tel) > 7 ? $tel : null, $this->direccion, $this->municipio_id, null,
            $licencia?->categoriaLicencia?->codigo, $licencia?->numero, $licencia?->fecha_vencimiento, esConductor: true);
    }
}
