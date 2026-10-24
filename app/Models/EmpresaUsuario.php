<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pertenencia de un usuario a una empresa (tabla empresa_user).
 */
class EmpresaUsuario extends Pivot
{
    protected $table = 'empresa_user';

    public $incrementing = true;

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function oficina(): BelongsTo
    {
        return $this->belongsTo(Oficina::class);
    }
}
