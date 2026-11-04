<?php

namespace App\Models;

use App\Enums\TipoConsultaTransito;
use Illuminate\Database\Eloquent\Model;

/**
 * Proveedor de consultas de tránsito de la PLATAFORMA (una sola fila): la API key es de la
 * plataforma, que revende las consultas a las empresas. El costo real solo lo ve el superadministrador.
 */
class ProveedorTransito extends Model
{
    protected $table = 'proveedor_transito';

    protected $fillable = ['proveedor', 'url', 'api_key', 'activo', 'costo_credito', 'creditos', 'precios'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'activo' => 'boolean',
            'costo_credito' => 'decimal:2',
            'creditos' => 'array',
            'precios' => 'array',
        ];
    }

    public static function actual(): self
    {
        return self::query()->first() ?? new self([
            'proveedor' => 'placapi',
            'url' => 'https://placapi.com',
            'activo' => false,
            'costo_credito' => 349,
        ]);
    }

    public function configurado(): bool
    {
        return $this->activo && filled($this->api_key);
    }

    public function creditosDe(TipoConsultaTransito $tipo): int
    {
        return (int) ($this->creditos[$tipo->value] ?? $tipo->creditosPorDefecto());
    }

    /** Costo real de una consulta de ese tipo (créditos × costo del crédito). */
    public function costoDe(TipoConsultaTransito $tipo): float
    {
        return round($this->creditosDe($tipo) * (float) $this->costo_credito, 2);
    }

    /** Precio de venta por defecto de ese tipo (null = sin definir). */
    public function precioDe(TipoConsultaTransito $tipo): ?float
    {
        $precio = $this->precios[$tipo->value] ?? null;

        return $precio === null || $precio === '' ? null : (float) $precio;
    }
}
