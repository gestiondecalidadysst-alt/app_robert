<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Un envío al RNDC (o el registro manual de un radicado obtenido en la web del RNDC). */
class RegistroRndc extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'registros_rndc';

    protected $fillable = [
        'empresa_id', 'registrable_type', 'registrable_id', 'proceso', 'estado', 'ingresoid', 'ambiente',
        'variables', 'respuesta', 'errores', 'user_id', 'duracion_ms',
    ];

    protected function casts(): array
    {
        return ['variables' => 'array', 'errores' => 'array', 'proceso' => 'integer'];
    }

    public function registrable(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes();
    }

    public function etiquetaEstado(): string
    {
        return ['registrado' => 'Registrado', 'manual' => 'Registrado en la web', 'error' => 'Con errores'][$this->estado] ?? $this->estado;
    }
}
