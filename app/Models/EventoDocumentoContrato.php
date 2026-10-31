<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de evidencias del documento del contrato: emitido, enviado, visto, código enviado,
 * firmado, anulado… Solo se agregan, nunca se modifican.
 */
class EventoDocumentoContrato extends Model
{
    public const UPDATED_AT = null;

    public const NOMBRES = [
        'emitido' => 'Documento emitido',
        'enviado' => 'Enlace de firma enviado',
        'visto' => 'Abierto por el cliente',
        'codigo_enviado' => 'Código de verificación enviado',
        'codigo_fallido' => 'Código incorrecto',
        'codigo_bloqueado' => 'Código bloqueado por intentos fallidos',
        'firmado' => 'Firmado por el cliente',
        'recordatorio' => 'Recordatorio enviado',
        'vencido' => 'Enlace vencido sin firma',
        'anulado' => 'Documento anulado',
    ];

    protected $table = 'eventos_documento_contrato';

    protected $fillable = ['documento_contrato_id', 'evento', 'ip', 'user_agent', 'detalle', 'user_id'];

    protected function casts(): array
    {
        return ['detalle' => 'array', 'created_at' => 'datetime'];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoContrato::class, 'documento_contrato_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getNombreAttribute(): string
    {
        return self::NOMBRES[$this->evento] ?? $this->evento;
    }
}
