<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una ocurrencia concreta de un error del sistema (ver App\Support\Sistema\RegistroErrores). */
class OcurrenciaError extends Model
{
    protected $table = 'ocurrencias_error';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ocurrio_at' => 'datetime'];
    }

    public function error(): BelongsTo
    {
        return $this->belongsTo(ErrorSistema::class, 'error_sistema_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
