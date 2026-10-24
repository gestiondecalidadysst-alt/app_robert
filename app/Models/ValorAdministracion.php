<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cuota de administración por clase de vehículo, vigente desde una fecha.
 */
class ValorAdministracion extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'valores_administracion';

    protected $fillable = ['empresa_id', 'clase_vehiculo_id', 'valor', 'vigente_desde'];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vigente_desde' => 'date',
        ];
    }

    public function claseVehiculo(): BelongsTo
    {
        return $this->belongsTo(ClaseVehiculo::class);
    }

    /** Valor vigente para una clase en una fecha (por defecto hoy) dentro de una empresa. */
    public static function vigente(Empresa|int $empresa, int $claseVehiculoId, ?Carbon $fecha = null): ?self
    {
        return static::query()
            ->deEmpresa($empresa)
            ->where('clase_vehiculo_id', $claseVehiculoId)
            ->whereDate('vigente_desde', '<=', ($fecha ?? now())->toDateString())
            ->orderByDesc('vigente_desde')
            ->first();
    }
}
