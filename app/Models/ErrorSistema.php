<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Error inesperado agrupado por huella (ver App\Support\Sistema\RegistroErrores). */
class ErrorSistema extends Model
{
    protected $table = 'errores_sistema';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'primera_vez' => 'datetime',
            'ultima_vez' => 'datetime',
            'alertado_at' => 'datetime',
            'resuelto_at' => 'datetime',
            'ocurrencias' => 'integer',
            'linea' => 'integer',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Historial de cada vez que ocurrió, de la más reciente a la más antigua. */
    public function ocurrencias(): HasMany
    {
        return $this->hasMany(OcurrenciaError::class, 'error_sistema_id')->orderByDesc('ocurrio_at');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->whereNull('resuelto_at');
    }

    public function getClaseCortaAttribute(): string
    {
        return class_basename($this->clase);
    }
}
