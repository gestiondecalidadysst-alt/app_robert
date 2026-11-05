<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Costo mínimo del SICE-TAC para una ruta y una configuración vehicular. */
class TarifaSicetac extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'tarifas_sicetac';

    protected $fillable = [
        'empresa_id', 'origen_id', 'destino_id', 'configuracion', 'valor_minimo', 'vigente_desde', 'vigente_hasta',
    ];

    protected function casts(): array
    {
        return ['valor_minimo' => 'integer', 'configuracion' => 'integer',
            'vigente_desde' => 'date', 'vigente_hasta' => 'date'];
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'destino_id');
    }

    public function vigente(?\DateTimeInterface $fecha = null): bool
    {
        $fecha = $fecha ?? now();

        return $this->vigente_desde <= $fecha && ($this->vigente_hasta === null || $this->vigente_hasta >= $fecha);
    }
}
