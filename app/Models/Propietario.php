<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Propietario (afiliado) de uno o varios vehículos. Puede ser persona natural
 * o jurídica (la propia empresa también puede ser propietaria).
 */
class Propietario extends Model
{
    use Auditable, PerteneceAEmpresa, SoftDeletes;

    public const GENEROS = ['F' => 'Femenino', 'M' => 'Masculino', 'O' => 'Otro'];

    protected $table = 'propietarios';

    protected $fillable = [
        'empresa_id', 'tipo_identificacion_id', 'documento', 'nombres', 'apellidos', 'fecha_nacimiento',
        'genero', 'fecha_ingreso', 'telefono', 'email', 'direccion', 'municipio_id', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_ingreso' => 'date',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Propietario $propietario): void {
            $propietario->documento = preg_replace('/[^0-9A-Za-z]/', '', (string) $propietario->documento);
        });
    }

    public function tipoIdentificacion(): BelongsTo
    {
        return $this->belongsTo(TipoIdentificacion::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombres . ' ' . $this->apellidos);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('activo'), true);
    }
}
