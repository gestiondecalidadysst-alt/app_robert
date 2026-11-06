<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Cuenta de cobro mensual de administración de un vehículo. Se genera una por mes y vehículo
 * activo; los pagos la van abonando. El saldo es lo que falta por pagar de ese mes.
 */
class CuotaAdministracion extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'cuotas_administracion';

    protected $fillable = [
        'empresa_id', 'vehiculo_id', 'propietario_id', 'clase_vehiculo_id',
        'periodo', 'valor', 'pagado', 'anulado_at', 'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return ['periodo' => 'date', 'valor' => 'integer', 'pagado' => 'integer', 'anulado_at' => 'datetime'];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class);
    }

    public function pagos(): BelongsToMany
    {
        return $this->belongsToMany(PagoAdministracion::class, 'cuota_administracion_pago')
            ->withPivot('valor');
    }

    public function anulada(): bool
    {
        return $this->anulado_at !== null;
    }

    public function saldo(): int
    {
        return $this->anulada() ? 0 : max(0, (int) $this->valor - (int) $this->pagado);
    }

    public function estado(): string
    {
        return match (true) {
            $this->anulada() => 'anulada',
            $this->saldo() <= 0 => 'pagada',
            $this->pagado > 0 => 'parcial',
            default => 'pendiente',
        };
    }

    public function etiquetaEstado(): string
    {
        return ['anulada' => 'Anulada', 'pagada' => 'Pagada', 'parcial' => 'Abonada', 'pendiente' => 'Pendiente'][$this->estado()];
    }

    /** ¿El periodo es anterior al mes en curso? (lo que de verdad está en mora) */
    public function enMora(): bool
    {
        return $this->saldo() > 0 && $this->periodo->lt(now()->startOfMonth());
    }

    public function periodoTexto(): string
    {
        return ucfirst($this->periodo->translatedFormat('F Y'));
    }
}
