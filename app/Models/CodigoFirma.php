<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código de verificación de un solo uso para firmar un documento. Solo se guarda su huella.
 */
class CodigoFirma extends Model
{
    public const MINUTOS_VIGENCIA = 10;

    public const MAX_INTENTOS = 5;

    /** Máximo de códigos por documento en la ventana de tiempo (evita gastar saldo de SMS). */
    public const MAX_POR_VENTANA = 3;

    public const MINUTOS_VENTANA = 15;

    protected $table = 'codigos_firma';

    protected $fillable = ['documento_contrato_id', 'canal', 'destino', 'codigo_hash', 'expira_at', 'intentos', 'usado_at', 'ip'];

    protected $hidden = ['codigo_hash'];

    protected function casts(): array
    {
        return ['expira_at' => 'datetime', 'usado_at' => 'datetime', 'intentos' => 'integer'];
    }

    public static function huella(string $codigo): string
    {
        return hash_hmac('sha256', $codigo, (string) config('app.key'));
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoContrato::class, 'documento_contrato_id');
    }

    public function disponible(): bool
    {
        return $this->usado_at === null && $this->expira_at->isFuture() && $this->intentos < self::MAX_INTENTOS;
    }

    public function coincide(string $codigo): bool
    {
        return hash_equals($this->codigo_hash, self::huella(preg_replace('/\D/', '', $codigo)));
    }
}
