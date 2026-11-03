<?php

namespace App\Models;

use App\Enums\MetodoPago;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago o abono de un contrato, con su recibo de caja:
 * - Número consecutivo por empresa (prefijo + número) y código para el QR de verificación.
 * - saldo_anterior / saldo_despues: el saldo del contrato antes y después de este pago (lo que
 *   dice el recibo, aunque después se anule otro pago).
 * - No se borra: se anula con motivo. Saldo del contrato = valor − pagos no anulados.
 */
class PagoContrato extends Model
{
    use Auditable, PerteneceAEmpresa;

    public const PAGO_TOTAL = 'total';

    public const ABONO_FINAL = 'abono_final';

    public const ABONO = 'abono';

    protected $table = 'pagos_contrato';

    protected $fillable = ['empresa_id', 'contrato_id', 'fecha', 'valor', 'metodo', 'referencia', 'observacion', 'user_id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valor' => 'decimal:2',
            'saldo_anterior' => 'decimal:2',
            'saldo_despues' => 'decimal:2',
            'metodo' => MetodoPago::class,
            'numero' => 'integer',
            'anulado_at' => 'datetime',
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

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function anulado(): bool
    {
        return $this->anulado_at !== null;
    }

    /** RC-0001 */
    public function getNumeroReciboAttribute(): string
    {
        return ($this->prefijo ? $this->prefijo . '-' : '') . str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * total: pagó el contrato completo en un solo pago · abono_final: abono que deja el saldo en cero
     * · abono: queda saldo pendiente.
     */
    public function tipo(): string
    {
        $valorContrato = (float) ($this->contrato?->valor ?? 0);

        return match (true) {
            (float) $this->saldo_despues > 0 => self::ABONO,
            round((float) $this->saldo_anterior, 2) >= round($valorContrato, 2) => self::PAGO_TOTAL,
            default => self::ABONO_FINAL,
        };
    }

    public function tipoEtiqueta(): string
    {
        return match ($this->tipo()) {
            self::PAGO_TOTAL => 'Pago total',
            self::ABONO_FINAL => 'Abono final',
            default => 'Abono',
        };
    }

    public function urlVerificacion(): string
    {
        return route('recibo.verificar', ['codigo' => $this->codigo_verificacion]);
    }

    public function urlPdf(): string
    {
        return route('recibo.pdf', ['codigo' => $this->codigo_verificacion]);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('anulado_at'));
    }
}
