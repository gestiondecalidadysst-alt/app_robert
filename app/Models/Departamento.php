<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Departamento (DANE).
 */
class Departamento extends Catalogo
{
    protected $table = 'departamentos';

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class);
    }
}
