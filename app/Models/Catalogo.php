<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de los catálogos nacionales (compartidos por todas las empresas).
 */
abstract class Catalogo extends Model
{
    use Auditable;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('activo'), true);
    }
}
