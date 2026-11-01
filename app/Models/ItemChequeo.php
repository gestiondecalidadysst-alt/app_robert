<?php

namespace App\Models;

use App\Enums\TipoInspeccion;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Ítem de la lista de chequeo de la empresa (preoperacional o inspección mensual).
 * Cada inspección guarda una copia de los ítems: cambiar o desactivar uno no altera las anteriores.
 */
class ItemChequeo extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'items_chequeo';

    protected $fillable = ['empresa_id', 'tipo', 'grupo', 'descripcion', 'critico', 'orden', 'activo'];

    protected $attributes = ['critico' => false, 'activo' => true, 'orden' => 0];

    protected function casts(): array
    {
        return [
            'tipo' => TipoInspeccion::class,
            'critico' => 'boolean',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('activo'), true);
    }

    public function scopeDeTipo(Builder $query, TipoInspeccion $tipo): Builder
    {
        return $query->where($query->qualifyColumn('tipo'), $tipo->value);
    }

    public function scopeOrdenados(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('id');
    }
}
