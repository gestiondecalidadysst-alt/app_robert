<?php

namespace App\Models;

/**
 * Departamento (DANE).
 */
class Departamento extends Catalogo
{
    protected $table = 'departamentos';

    public function municipios(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Municipio::class);
    }
}
