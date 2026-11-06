<?php

namespace App\Models;

use App\Enums\MetodoPago;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Pago de la cuota de administración de un vehículo. Genera un recibo con número consecutivo
 * propio y se reparte entre las cuotas pendientes (las más viejas primero).
 */
class PagoAdministracion extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'pagos_administracion';

    protected $fillable = [
        'empresa_id', 'vehiculo_id', 'numero', 'fecha', 'valor', 'metodo',
        'referencia', 'observacion', 'user_id', 'anulado_at', 'motivo_anulacion', 'anulado_por',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'valor' => 'integer', 'numero' => 'integer', 'metodo' => MetodoPago::class, 'anulado_at' => 'datetime'];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes();
    }

    public function cuotas(): BelongsToMany
    {
        return $this->belongsToMany(CuotaAdministracion::class, 'cuota_administracion_pago')
            ->withPivot('valor');
    }

    public function anulado(): bool
    {
        return $this->anulado_at !== null;
    }

    public function getNumeroReciboAttribute(): string
    {
        return 'ADM-'.str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT);
    }
}
