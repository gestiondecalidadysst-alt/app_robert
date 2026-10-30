<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Nit;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasCurrentTenantLabel;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Empresa de transporte cliente de la plataforma (tenant).
 */
class Empresa extends Model implements HasAvatar, HasCurrentTenantLabel, HasName
{
    use Auditable;

    protected $table = 'empresas';

    protected $fillable = [
        'slug', 'tipo_identificacion_id', 'nit', 'dv', 'razon_social', 'nombre_comercial',
        'municipio_id', 'direccion', 'telefono', 'email', 'representante_legal',
        'representante_documento', 'resolucion_habilitacion', 'anio_habilitacion',
        'codigo_territorial', 'logo_path', 'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'dv' => 'integer',
            'anio_habilitacion' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Empresa $empresa): void {
            $empresa->nit = Nit::limpiar($empresa->nit);
            $empresa->dv = Nit::digitoVerificacion($empresa->nit);

            if (blank($empresa->slug)) {
                $empresa->slug = static::slugUnico($empresa->nombre_comercial ?: $empresa->razon_social);
            }
        });
    }

    public static function slugUnico(string $texto): string
    {
        $base = Str::slug($texto) ?: 'empresa';
        $slug = $base;
        $i = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    // Relaciones

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(EmpresaUsuario::class)
            ->withPivot(['oficina_id', 'activo'])
            ->withTimestamps();
    }

    public function oficinas(): HasMany
    {
        return $this->hasMany(Oficina::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Rol::class);
    }

    public function valoresAdministracion(): HasMany
    {
        return $this->hasMany(ValorAdministracion::class);
    }

    public function empresasConvenio(): HasMany
    {
        return $this->hasMany(EmpresaConvenio::class);
    }

    public function propietarios(): HasMany
    {
        return $this->hasMany(Propietario::class);
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    public function documentosVehiculo(): HasMany
    {
        return $this->hasMany(DocumentoVehiculo::class);
    }

    public function novedadesVehiculo(): HasMany
    {
        return $this->hasMany(NovedadVehiculo::class);
    }

    public function conductores(): HasMany
    {
        return $this->hasMany(Conductor::class);
    }

    public function documentosConductor(): HasMany
    {
        return $this->hasMany(DocumentoConductor::class);
    }

    public function novedadesConductor(): HasMany
    {
        return $this->hasMany(NovedadConductor::class);
    }

    public function asignacionesVehiculo(): HasMany
    {
        return $this->hasMany(AsignacionVehiculo::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function pagosContrato(): HasMany
    {
        return $this->hasMany(PagoContrato::class);
    }

    public function fuecs(): HasMany
    {
        return $this->hasMany(Fuec::class);
    }

    /** Datos de habilitación que exige el número del FUEC (art. 4 Res. 6652/2019) que faltan. */
    public function faltantesHabilitacion(): array
    {
        return array_keys(array_filter([
            'código de la dirección territorial' => blank($this->codigo_territorial),
            'número de la resolución de habilitación' => blank($this->resolucion_habilitacion),
            'año de habilitación' => blank($this->anio_habilitacion),
        ]));
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function tipoIdentificacion(): BelongsTo
    {
        return $this->belongsTo(TipoIdentificacion::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    // Filament

    public function getFilamentName(): string
    {
        return $this->nombre_comercial ?: $this->razon_social;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function getCurrentTenantLabel(): string
    {
        return 'Empresa';
    }

    public function getNitFormateadoAttribute(): string
    {
        return Nit::formatear($this->nit, $this->dv);
    }
}
