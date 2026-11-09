<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una cotización: una fila de la tabla Fecha / Ruta / Duración / Valor.
 */
class CotizacionItem extends Model
{
    protected $table = 'cotizacion_items';

    public $timestamps = false;

    protected $fillable = ['cotizacion_id', 'fecha_texto', 'ruta', 'detalle', 'valor', 'orden'];

    protected function casts(): array
    {
        return ['valor' => 'integer', 'orden' => 'integer'];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }
}
