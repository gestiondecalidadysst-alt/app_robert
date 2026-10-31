<?php

namespace App\Models;

use App\Enums\EstadoVigencia;
use App\Enums\FormaPago;
use App\Enums\ObjetoContrato;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contrato de transporte especial con un cliente. Los FUEC se expiden contra un contrato.
 * El número es un consecutivo por empresa (App\Actions\Operacion\GuardarContrato).
 * No se borra: se anula.
 */
class Contrato extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'contratos';

    protected $fillable = [
        'empresa_id', 'cliente_id', 'objeto', 'descripcion_objeto', 'fecha_inicio', 'fecha_fin', 'hora_inicio',
        'hora_fin', 'origen_municipio_id', 'destino_municipio_id', 'recorrido', 'disposicion_total',
        'zonas_disposicion', 'cantidad_vehiculos', 'cantidad_pasajeros', 'responsable_nombre',
        'responsable_documento', 'responsable_telefono', 'responsable_direccion', 'valor', 'forma_pago',
        'fecha_limite_pago', 'oficina_id', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'objeto' => ObjetoContrato::class,
            'forma_pago' => FormaPago::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'fecha_limite_pago' => 'date',
            'disposicion_total' => 'boolean',
            'valor' => 'decimal:2',
            'anulado_at' => 'datetime',
            'numero' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'origen_municipio_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'destino_municipio_id');
    }

    public function oficina(): BelongsTo
    {
        return $this->belongsTo(Oficina::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoContrato::class);
    }

    public function fuecs(): HasMany
    {
        return $this->hasMany(Fuec::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoContrato::class)->latest('id');
    }

    /** Documento emitido vigente (no anulado), si lo hay. */
    public function documentoActivo(): ?DocumentoContrato
    {
        return $this->relationLoaded('documentos')
            ? $this->documentos->first(fn (DocumentoContrato $d) => ! $d->anulado())
            : $this->documentos()->activos()->first();
    }

    // Estado y saldo

    public function anulado(): bool
    {
        return $this->anulado_at !== null;
    }

    public function estado(?\DateTimeInterface $hoy = null): EstadoVigencia
    {
        return EstadoVigencia::para($this->fecha_inicio, $this->fecha_fin, $this->anulado(), $hoy);
    }

    /** Total abonado (usa pagos_sum_valor si se cargó con withSum). */
    public function totalPagado(): float
    {
        return (float) ($this->pagos_sum_valor ?? $this->pagos()->sum('valor'));
    }

    public function saldo(): float
    {
        return max(0, round((float) $this->valor - $this->totalPagado(), 2));
    }

    /** Número visible: 0001, 0245… */
    public function getNumeroFormateadoAttribute(): string
    {
        return str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT);
    }

    public function getVigenciaAttribute(): string
    {
        return $this->fecha_inicio->format('d/m/Y') . ' – ' . $this->fecha_fin->format('d/m/Y');
    }

    // Scopes

    public function scopeNoAnulados(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('anulado_at'));
    }

    /** Contratos con los que se puede expedir un FUEC hoy o en adelante. */
    public function scopeDisponiblesParaFuec(Builder $query): Builder
    {
        return $query->noAnulados()->whereDate($query->qualifyColumn('fecha_fin'), '>=', today());
    }
}
