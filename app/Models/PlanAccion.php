<?php

namespace App\Models;

use App\Enums\EstadoPlan;
use App\Enums\OrigenPlan;
use App\Enums\PrioridadPlan;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\Concerns\TieneEvidencias;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan de acción: seguimiento de la empresa a una falla (preoperacional, inspección mensual),
 * una rutina preventiva vencida o un hallazgo registrado a mano. Lo ejecuta el propietario;
 * se cierra con evidencia y la verificación de la empresa (App\Actions\Mantenimiento\GestionarPlanAccion).
 *
 * Mientras un plan de prioridad ALTA esté pendiente, el vehículo no queda APTO en el preoperacional.
 */
class PlanAccion extends Model
{
    use Auditable, PerteneceAEmpresa, TieneEvidencias;

    protected $table = 'planes_accion';

    protected $fillable = [
        'empresa_id', 'vehiculo_id', 'origen', 'inspeccion_id', 'rutina_mantenimiento_id', 'prioridad', 'titulo',
        'hallazgos', 'accion', 'responsable', 'fecha_limite', 'estado', 'user_id',
    ];

    protected $attributes = ['estado' => 'abierto', 'reincidencias' => 0];

    protected function casts(): array
    {
        return [
            'origen' => OrigenPlan::class,
            'prioridad' => PrioridadPlan::class,
            'estado' => EstadoPlan::class,
            'hallazgos' => 'array',
            'fecha_limite' => 'date',
            'cerrado_at' => 'datetime',
            'reincidencias' => 'integer',
            'numero' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Consecutivo por empresa, como contratos, cotizaciones, remesas y pagos.
        static::creating(function (self $plan): void {
            if (! $plan->numero) {
                $plan->numero = ((int) self::query()->withoutGlobalScopes()
                    ->where('empresa_id', $plan->empresa_id)->max('numero')) + 1;
            }
        });
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    public function inspeccion(): BelongsTo
    {
        return $this->belongsTo(Inspeccion::class);
    }

    public function rutina(): BelongsTo
    {
        return $this->belongsTo(RutinaMantenimiento::class, 'rutina_mantenimiento_id');
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class)->whereNull('anulado_at');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function pendiente(): bool
    {
        return $this->estado->pendiente();
    }

    public function vencido(): bool
    {
        return $this->pendiente() && $this->fecha_limite !== null && $this->fecha_limite->lt(today());
    }

    /** Número visible: PA-00001, PA-00245… Es consecutivo por empresa, no de toda la plataforma. */
    public function getNumeroFormateadoAttribute(): string
    {
        return 'PA-'.str_pad((string) $this->numero, 5, '0', STR_PAD_LEFT);
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('estado'), EstadoPlan::pendientes());
    }
}
