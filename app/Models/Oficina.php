<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Oficina o sede de una empresa.
 */
class Oficina extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'oficinas';

    protected $fillable = ['empresa_id', 'nombre', 'municipio_id', 'direccion', 'telefono', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}
