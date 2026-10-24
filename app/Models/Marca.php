<?php

namespace App\Models;

/**
 * Marca de vehículo.
 */
class Marca extends Catalogo
{
    protected $table = 'marcas';

    public function lineas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Linea::class);
    }
}
