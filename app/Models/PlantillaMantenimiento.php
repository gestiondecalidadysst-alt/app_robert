<?php

namespace App\Models;

use App\Enums\CriterioPlantilla;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plantilla de plan de mantenimiento preventivo (plan modelo). Se asigna a los vehículos por clase,
 * por marca y línea, o a mano (libre). La plantilla "por defecto" aplica a los vehículos sin otra.
 * Ver App\Support\Mantenimiento\PlanPreventivo::plantillaDe().
 */
class PlantillaMantenimiento extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'plantillas_mantenimiento';

    protected $fillable = ['empresa_id', 'nombre', 'criterio', 'clase_vehiculo_id', 'marca_id', 'linea_id', 'descripcion', 'por_defecto', 'activo'];

    protected $attributes = ['criterio' => 'libre', 'por_defecto' => false, 'activo' => true];

    protected function casts(): array
    {
        return [
            'criterio' => CriterioPlantilla::class,
            'por_defecto' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function rutinas(): HasMany
    {
        return $this->hasMany(RutinaMantenimiento::class)->orderBy('orden')->orderBy('id');
    }

    public function claseVehiculo(): BelongsTo
    {
        return $this->belongsTo(ClaseVehiculo::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function linea(): BelongsTo
    {
        return $this->belongsTo(Linea::class);
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    public function getAplicaAAttribute(): string
    {
        return match (true) {
            $this->por_defecto => 'Vehículos sin otra plantilla',
            $this->criterio === CriterioPlantilla::Clase => 'Clase ' . ($this->claseVehiculo?->nombre ?? '—'),
            $this->criterio === CriterioPlantilla::MarcaLinea => trim(($this->marca?->nombre ?? '—') . ' ' . ($this->linea?->nombre ?? '(todas las líneas)')),
            default => 'Vehículos asignados a mano',
        };
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('activo'), true);
    }
}
