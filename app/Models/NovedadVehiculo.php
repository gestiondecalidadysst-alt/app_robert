<?php

namespace App\Models;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoNovedadVehiculo;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de cambios de estado de un vehículo (suspensión, taller, desvinculación…).
 * Se crea únicamente con App\Actions\Flota\CambiarEstadoVehiculo.
 */
class NovedadVehiculo extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'novedades_vehiculo';

    protected $fillable = [
        'empresa_id', 'vehiculo_id', 'tipo', 'fecha', 'estado_anterior', 'estado_nuevo',
        'fecha_reactivacion', 'observacion', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoNovedadVehiculo::class,
            'estado_anterior' => EstadoVehiculo::class,
            'estado_nuevo' => EstadoVehiculo::class,
            'fecha' => 'date',
            'fecha_reactivacion' => 'date',
        ];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
