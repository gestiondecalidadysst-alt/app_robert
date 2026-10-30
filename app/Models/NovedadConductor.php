<?php

namespace App\Models;

use App\Enums\EstadoConductor;
use App\Enums\TipoNovedadConductor;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de cambios de estado de un conductor. Se crea con CambiarEstadoConductor.
 */
class NovedadConductor extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'novedades_conductor';

    protected $fillable = [
        'empresa_id', 'conductor_id', 'tipo', 'fecha', 'estado_anterior', 'estado_nuevo',
        'fecha_reactivacion', 'observacion', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoNovedadConductor::class,
            'estado_anterior' => EstadoConductor::class,
            'estado_nuevo' => EstadoConductor::class,
            'fecha' => 'date',
            'fecha_reactivacion' => 'date',
        ];
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
