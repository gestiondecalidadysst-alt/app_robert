<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Marca de vehículo.
 */
class Marca extends Catalogo
{
    protected $table = 'marcas';

    public function lineas(): HasMany
    {
        return $this->hasMany(Linea::class);
    }
}
