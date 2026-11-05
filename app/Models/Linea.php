<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de vehículo (modelo comercial de una marca).
 */
class Linea extends Catalogo
{
    protected $table = 'lineas';

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }
}
