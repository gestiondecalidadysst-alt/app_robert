<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un conductor a un vehículo. Vigente mientras no tenga fecha_hasta.
 * Se crea y termina con App\Actions\Conductores\AsignarVehiculo.
 */
class AsignacionVehiculo extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'asignaciones_vehiculo';

    protected $fillable = [
        'empresa_id', 'conductor_id', 'vehiculo_id', 'fecha_desde', 'fecha_hasta', 'principal', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_desde' => 'date',
            'fecha_hasta' => 'date',
            'principal' => 'boolean',
        ];
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class)->withTrashed();
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    public function vigente(): bool
    {
        return $this->fecha_hasta === null;
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('fecha_hasta'));
    }
}
