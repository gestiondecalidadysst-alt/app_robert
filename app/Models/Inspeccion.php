<?php

namespace App\Models;

use App\Enums\ResultadoInspeccion;
use App\Enums\TipoInspeccion;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Preoperacional o inspección mensual de un vehículo. Se registra con
 * App\Actions\Pesv\RegistrarInspeccion y no se edita: guarda la copia de los ítems, de los
 * datos del vehículo y del conductor, y de la firma del conductor vigente en ese momento.
 */
class Inspeccion extends Model
{
    use Auditable, PerteneceAEmpresa;

    public const DISCO = 'local';

    protected $table = 'inspecciones';

    protected $fillable = [
        'empresa_id', 'tipo', 'vehiculo_id', 'conductor_id', 'fuec_id', 'fecha', 'kilometraje', 'resultado',
        'respuestas', 'datos', 'observaciones', 'firma_path', 'codigo_verificacion', 'ip', 'user_agent',
        'latitud', 'longitud', 'user_id',
    ];

    protected array $noAuditar = ['respuestas', 'datos', 'user_agent'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoInspeccion::class,
            'resultado' => ResultadoInspeccion::class,
            'fecha' => 'date',
            'respuestas' => 'array',
            'datos' => 'array',
            'kilometraje' => 'integer',
            'latitud' => 'float',
            'longitud' => 'float',
        ];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class)->withTrashed();
    }

    public function fuec(): BelongsTo
    {
        return $this->belongsTo(Fuec::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Planes de acción abiertos a partir de los hallazgos de este preoperacional. */
    public function planesAccion(): HasMany
    {
        return $this->hasMany(PlanAccion::class);
    }

    public function apta(): bool
    {
        return $this->resultado === ResultadoInspeccion::Apto;
    }

    /** Ítems que no cumplieron. */
    public function fallas(): array
    {
        return array_values(array_filter($this->respuestas ?? [], fn (array $r) => ($r['respuesta'] ?? null) === 'no'));
    }

    public function getPlacaAttribute(): ?string
    {
        return $this->datos['vehiculo']['placa'] ?? $this->vehiculo?->placa;
    }

    public function getConductorNombreAttribute(): ?string
    {
        return $this->datos['conductor']['nombre'] ?? $this->conductor?->nombre_completo;
    }

    /**
     * Número del extracto (FUEC) para el que se hizo el preoperacional.
     *
     * Se prefiere el FUEC vivo (número formateado para leer); si ya no existe, se usa el
     * que quedó en el snapshot del preoperacional. Null si no se hizo contra un FUEC.
     */
    public function numeroExtracto(): ?string
    {
        return $this->fuec?->numero_formateado ?? ($this->datos['fuec']['numero'] ?? null);
    }

    /** Número del contrato al que pertenece ese extracto. */
    public function numeroContrato(): ?string
    {
        return $this->fuec?->contrato?->numero_formateado ?? ($this->datos['fuec']['contrato'] ?? null);
    }

    public function urlVerificacion(): string
    {
        return route('inspeccion.verificar', ['codigo' => $this->codigo_verificacion]);
    }

    public function scopePreoperacionales(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('tipo'), TipoInspeccion::Preoperacional->value);
    }

    public function scopeDelDia(Builder $query, ?\DateTimeInterface $fecha = null): Builder
    {
        return $query->whereDate($query->qualifyColumn('fecha'), ($fecha ?? now())->format('Y-m-d'));
    }
}
