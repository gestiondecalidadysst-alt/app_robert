<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de mensajes enviados (SMS y correo). El destino se guarda enmascarado y el
 * contenido no se guarda (puede llevar códigos o enlaces de firma).
 */
class EnvioMensaje extends Model
{
    use PerteneceAEmpresa;

    public const TIPOS = [
        'enlace_firma' => 'Enlace de firma',
        'codigo_firma' => 'Código de verificación',
        'contrato_firmado' => 'Contrato firmado',
        'recordatorio_firma' => 'Recordatorio de firma',
        'recibo_pago' => 'Recibo de pago',
        'estado_cuenta' => 'Estado de cuenta / paz y salvo',
        'prueba' => 'Mensaje de prueba',
    ];

    protected $table = 'envios_mensaje';

    protected $fillable = [
        'empresa_id', 'documento_contrato_id', 'tipo', 'canal', 'destino', 'estado', 'proveedor',
        'referencia_proveedor', 'error', 'intentos', 'enviado_at',
    ];

    protected function casts(): array
    {
        return ['enviado_at' => 'datetime', 'intentos' => 'integer'];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoContrato::class, 'documento_contrato_id');
    }

    public function getTipoNombreAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
