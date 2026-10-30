<?php

namespace App\Models;

use App\Enums\MetodoPago;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abono o pago de un contrato. Saldo del contrato = valor − suma de pagos.
 */
class PagoContrato extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'pagos_contrato';

    protected $fillable = ['empresa_id', 'contrato_id', 'fecha', 'valor', 'metodo', 'referencia', 'observacion', 'user_id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valor' => 'decimal:2',
            'metodo' => MetodoPago::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PagoContrato $pago): void {
            $pago->empresa_id ??= $pago->contrato?->empresa_id;
            $pago->user_id ??= auth()->id();
        });
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
