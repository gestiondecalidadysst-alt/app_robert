<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rutina de un plan de mantenimiento preventivo: cada cuántos km y/o meses (lo que ocurra primero).
 * Pertenece a una plantilla (plantilla_mantenimiento_id) o al plan personalizado de un vehículo
 * (vehiculo_id). Ver App\Support\Mantenimiento\PlanPreventivo.
 */
class RutinaMantenimiento extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'rutinas_mantenimiento';

    protected $fillable = [
        'empresa_id', 'plantilla_mantenimiento_id', 'nombre', 'clase_vehiculo_id', 'vehiculo_id', 'intervalo_km', 'intervalo_meses', 'descripcion', 'orden', 'activo',
    ];

    protected $attributes = ['activo' => true, 'orden' => 0];

    protected function casts(): array
    {
        return [
            'intervalo_km' => 'integer',
            'intervalo_meses' => 'integer',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaMantenimiento::class, 'plantilla_mantenimiento_id');
    }

    public function claseVehiculo(): BelongsTo
    {
        return $this->belongsTo(ClaseVehiculo::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    public function getAlcanceAttribute(): string
    {
        return match (true) {
            $this->vehiculo_id !== null => 'Plan del vehículo ' . $this->vehiculo?->placa,
            $this->plantilla_mantenimiento_id !== null => 'Plantilla ' . $this->plantilla?->nombre,
            default => 'Sin plan',
        };
    }

    public function getFrecuenciaAttribute(): string
    {
        return collect([
            $this->intervalo_km ? 'cada ' . number_format($this->intervalo_km, 0, ',', '.') . ' km' : null,
            $this->intervalo_meses ? 'cada ' . $this->intervalo_meses . ($this->intervalo_meses === 1 ? ' mes' : ' meses') : null,
        ])->filter()->implode(' o ') ?: 'Sin intervalo';
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('activo'), true);
    }
}
