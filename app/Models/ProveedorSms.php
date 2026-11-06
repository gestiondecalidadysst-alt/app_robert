<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cuenta de SMS de la PLATAFORMA (una sola fila): las credenciales de Onurix son de la
 * plataforma, que revende los mensajes a las empresas. El costo real solo lo ve el
 * superadministrador; cada empresa paga el precio de venta.
 */
class ProveedorSms extends Model
{
    protected $table = 'proveedor_sms';

    protected $fillable = ['proveedor', 'onurix_cliente', 'onurix_clave', 'activo', 'costo_segmento', 'precio_segmento'];

    protected $hidden = ['onurix_cliente', 'onurix_clave'];

    protected function casts(): array
    {
        return [
            'onurix_cliente' => 'encrypted',
            'onurix_clave' => 'encrypted',
            'activo' => 'boolean',
            'costo_segmento' => 'integer',
            'precio_segmento' => 'integer',
        ];
    }

    /** La fila única; si no existe aún, una en blanco (sin guardar). */
    public static function actual(): self
    {
        return self::query()->first() ?? new self(['proveedor' => 'onurix', 'activo' => false]);
    }

    /** Listo para enviar: activo y con credenciales. */
    public function configurado(): bool
    {
        return $this->activo && filled($this->onurix_cliente) && filled($this->onurix_clave);
    }
}
