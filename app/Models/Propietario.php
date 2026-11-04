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
class Propietario extends Model implements \App\Services\Rndc\RegistrableRndc
{
    use Auditable, PerteneceAEmpresa, \App\Models\Concerns\SeRegistraEnRndc, SoftDeletes;

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

    // RNDC (módulo de carga): se registra como tercero, proceso 11.

    public function procesoRndc(): int
    {
        return 11;
    }

    public function etiquetaRndc(): string
    {
        return trim($this->nombres . ' ' . $this->apellidos) . ' · ' . ($this->tipoIdentificacion?->codigo ?? '') . ' ' . $this->documento;
    }

    /** Nombres y apellidos como los pide el RNDC (el primer apellido y el resto). */
    private function apellidosRndc(): array
    {
        $partes = preg_split('/\s+/', trim((string) $this->apellidos), 2) ?: [];

        return [$partes[0] ?? null, $partes[1] ?? null];
    }

    public function variablesRndc(Empresa $empresa): array
    {
        $this->loadMissing(['tipoIdentificacion', 'municipio']);
        $c = \App\Support\Carga\CatalogoRndc::class;
        $tipo = $c::tipoId($this->tipoIdentificacion?->codigo);
        $tel = $c::digitos($this->telefono);
        [$a1, $a2] = $this->apellidosRndc();

        return array_filter([
            'NUMNITEMPRESATRANSPORTE' => $c::digitos($empresa->nit),
            'CODTIPOIDTERCERO' => $tipo,
            'NUMIDTERCERO' => $c::digitos($this->documento),
            'NOMIDTERCERO' => $c::texto($this->nombres, 100),
            'PRIMERAPELLIDOIDTERCERO' => $tipo === 'N' ? null : $c::texto($a1, 100),
            'SEGUNDOAPELLIDOIDTERCERO' => $tipo === 'N' ? null : $c::texto($a2, 100),
            'CODSEDETERCERO' => $tipo === 'N' ? '1' : null,
            'NOMSEDETERCERO' => $tipo === 'N' ? 'PRINCIPAL' : null,
            'NUMTELEFONOCONTACTO' => strlen($tel) <= 7 ? $tel : null,
            'NUMCELULARPERSONA' => strlen($tel) === 10 ? $tel : null,
            'NOMENCLATURADIRECCION' => $c::texto($this->direccion, 60),
            'CODMUNICIPIORNDC' => $c::municipio($this->municipio?->codigo_dane),
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function validarRndc(): array
    {
        $c = \App\Support\Carga\CatalogoRndc::class;
        $tel = $c::digitos($this->telefono);
        [$a1] = $this->apellidosRndc();

        return \App\Services\Rndc\ValidarTercero::errores($c::tipoId($this->tipoIdentificacion?->codigo), $this->documento, $this->nombres, $a1,
            strlen($tel) <= 7 ? $tel : null, strlen($tel) === 10 ? $tel : ($tel !== '' && strlen($tel) > 7 ? $tel : null), $this->direccion, $this->municipio_id);
    }
}
