<?php

namespace App\Models;

/**
 * Municipio (DANE).
 */
class Municipio extends Catalogo
{
    protected $table = 'municipios';

    public function departamento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /** Nombre con departamento: "MONTERIA (CORDOBA)". */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} ({$this->departamento?->nombre})";
    }
}
