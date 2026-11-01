<?php

namespace App\Models;

use App\Enums\AccionMantenimiento;
use App\Enums\TipoMantenimiento;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\Concerns\TieneEvidencias;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mantenimiento hecho al vehículo (lo realiza el propietario). Lo registran el auxiliar, el rol
 * Mantenimiento o el conductor desde su portal, con evidencias. Puede cumplir rutinas preventivas
 * y atender un plan de acción. No se edita: se anula. Ver App\Actions\Mantenimiento\RegistrarMantenimiento.
 */
class Mantenimiento extends Model
{
    use Auditable, PerteneceAEmpresa, TieneEvidencias;

    protected $table = 'mantenimientos';

    protected $fillable = [
        'empresa_id', 'vehiculo_id', 'fecha', 'kilometraje', 'tipo', 'accion', 'actividad', 'problema', 'repuestos', 'taller',
        'responsable_documento', 'responsable_nombre', 'valor', 'factura_numero', 'plan_accion_id', 'conductor_id', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'kilometraje' => 'integer',
            'tipo' => TipoMantenimiento::class,
            'accion' => AccionMantenimiento::class,
            'valor' => 'decimal:2',
            'anulado_at' => 'datetime',
        ];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    public function rutinas(): BelongsToMany
    {
        return $this->belongsToMany(RutinaMantenimiento::class, 'mantenimiento_rutina');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanAccion::class, 'plan_accion_id');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function anulado(): bool
    {
        return $this->anulado_at !== null;
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('anulado_at'));
    }
}
