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
        'representante_documento', 'representante_expedicion', 'sitio_web', 'resolucion_habilitacion',
        'anio_habilitacion', 'codigo_territorial', 'logo_path', 'firma_representante_path', 'pie_documentos_path',
        'activa',
        // Integraciones (pantalla Configuración → Integraciones)
        'sms_habilitado', 'onurix_cliente', 'onurix_clave', 'sms_limite_mensual',
        'firma_envio_automatico', 'firma_dias_enlace', 'firma_dias_recordatorio',
        'km_diarios_defecto', 'mantenimiento_dias_aviso', 'recibo_prefijo', 'recibo_numero_inicial',
        'jornada_max_horas_dia', 'jornada_max_horas_continuas', 'jornada_descanso_horas', 'planeacion_antelacion_horas', 'webhook_activo', 'webhook_url', 'webhook_secreto', 'webhook_eventos',
    ];

    /** Credenciales: nunca se envían al navegador ni a la auditoría. */
    protected $hidden = ['onurix_cliente', 'onurix_clave', 'webhook_url', 'webhook_secreto'];

    protected array $noAuditar = ['onurix_cliente', 'onurix_clave', 'webhook_url', 'webhook_secreto'];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'dv' => 'integer',
            'anio_habilitacion' => 'integer',
            'sms_habilitado' => 'boolean',
            'onurix_cliente' => 'encrypted',
            'onurix_clave' => 'encrypted',
            'sms_limite_mensual' => 'integer',
            'firma_envio_automatico' => 'boolean',
            'firma_dias_enlace' => 'integer',
            'firma_dias_recordatorio' => 'integer',
            'km_diarios_defecto' => 'integer',
            'mantenimiento_dias_aviso' => 'integer',
            'webhook_activo' => 'boolean',
            'webhook_url' => 'encrypted',
            'webhook_secreto' => 'encrypted',
            'webhook_eventos' => 'array',
        ];
    }

    /** Webhook (n8n) listo para usar. */
    public function webhookConfigurado(): bool
    {
        return $this->webhook_activo && filled($this->webhook_url);
    }

    /** Tiene SMS listo para usar (activado y con credenciales). */
    public function smsConfigurado(): bool
    {
        return $this->sms_habilitado && filled($this->onurix_cliente) && filled($this->onurix_clave);
    }

    public function plantillasContrato(): HasMany
    {
        return $this->hasMany(PlantillaContrato::class);
    }

    public function documentosContrato(): HasMany
    {
        return $this->hasMany(DocumentoContrato::class);
    }

    public function enviosMensaje(): HasMany
    {
        return $this->hasMany(EnvioMensaje::class);
    }

    /** Versión vigente de la plantilla de contrato (la de número mayor). */
    public function plantillaContratoVigente(): ?PlantillaContrato
    {
        return PlantillaContrato::query()->deEmpresa($this)->orderByDesc('version')->first();
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

    public function rutas(): HasMany
    {
        return $this->hasMany(Ruta::class);
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class);
    }

    public function fuecs(): HasMany
    {
        return $this->hasMany(Fuec::class);
    }

    /** Datos de habilitación que exige el número del FUEC (art. 4 Res. 6652/2019) que faltan. */
    public function faltantesHabilitacion(): array
    {
        return array_keys(array_filter([
            'dirección territorial' => blank($this->codigo_territorial),
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
