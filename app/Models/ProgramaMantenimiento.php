<?php

namespace App\Models;

use App\Enums\EstadoPrograma;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Programa anual de mantenimiento preventivo (PESV): actividades programadas por vehículo con fecha
 * y km estimados. Borrador → aprobado (línea base, con quién elaboró y aprobó) → reemplazado por
 * una nueva versión. Ver App\Actions\Mantenimiento\ProgramaAnual.
 */
class ProgramaMantenimiento extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'programas_mantenimiento';

    protected $fillable = ['empresa_id', 'anio', 'version', 'estado', 'codigo_formato', 'km_diarios_defecto', 'observaciones'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPrograma::class,
            'anio' => 'integer',
            'version' => 'integer',
            'km_diarios_defecto' => 'integer',
            'elaborado_at' => 'datetime',
            'aprobado_at' => 'datetime',
        ];
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(ActividadMantenimiento::class);
    }

    public function elaboradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'elaborado_por');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function aprobado(): bool
    {
        return $this->estado === EstadoPrograma::Aprobado;
    }

    public function getNombreAttribute(): string
    {
        return "Programa {$this->anio} · v{$this->version}";
    }
}
