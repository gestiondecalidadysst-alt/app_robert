<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Support\Nit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cliente (contratante) del servicio de transporte especial: empresa, colegio, persona…
 */
class Cliente extends Model
{
    use Auditable, PerteneceAEmpresa, SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'empresa_id', 'tipo_identificacion_id', 'documento', 'nombre', 'expedicion_municipio_id', 'direccion',
        'municipio_id', 'telefono', 'email', 'contacto', 'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'dv' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Cliente $cliente): void {
            $cliente->documento = preg_replace('/[^0-9A-Za-z]/', '', (string) $cliente->documento);
            $cliente->dv = $cliente->esNit() ? Nit::digitoVerificacion($cliente->documento) : null;
        });
    }

    public function esNit(): bool
    {
        $tipo = $this->relationLoaded('tipoIdentificacion')
            ? $this->tipoIdentificacion
            : TipoIdentificacion::find($this->tipo_identificacion_id);

        return (bool) $tipo?->es_empresa;
    }

    public function tipoIdentificacion(): BelongsTo
    {
        return $this->belongsTo(TipoIdentificacion::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function expedicionMunicipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'expedicion_municipio_id');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    /** "NIT 900.123.456-7" o "CC 1234567". */
    public function getIdentificacionAttribute(): string
    {
        $codigo = $this->tipoIdentificacion?->codigo;

        return trim($codigo.' '.($this->dv !== null ? Nit::formatear($this->documento) : $this->documento));
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('activo'), true);
    }
}
