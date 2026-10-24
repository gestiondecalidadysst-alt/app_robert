<?php

namespace App\Models;

/**
 * Línea de vehículo (modelo comercial de una marca).
 */
class Linea extends Catalogo
{
    protected $table = 'lineas';

    public function marca(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }
}
