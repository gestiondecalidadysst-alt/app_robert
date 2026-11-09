<?php

namespace App\Models;

use App\Enums\ModuloEmpresa;
use App\Enums\TipoConsultaTransito;
use App\Models\Concerns\Auditable;
use App\Support\Modulos;
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
        // Cotizaciones (diseño del PDF por empresa)
        'cotizacion_color', 'cotizacion_color_acento', 'cotizacion_validez_dias', 'cotizacion_incluye',
        'cotizacion_equipo', 'cotizacion_telefono_2', 'cotizacion_instagram', 'cotizacion_certificados',
        'cotizacion_sello_path',
        // Integraciones (pantalla Configuración → Integraciones)
        'sms_habilitado', 'onurix_cliente', 'onurix_clave', 'sms_limite_mensual', 'sms_precio',
        'firma_envio_automatico', 'firma_dias_enlace', 'firma_dias_recordatorio',
        'km_diarios_defecto', 'mantenimiento_dias_aviso', 'recibo_prefijo', 'recibo_numero_inicial',
        'jornada_max_horas_dia', 'jornada_max_horas_continuas', 'jornada_descanso_horas', 'planeacion_antelacion_horas',
        'modulos', 'rndc_usuario', 'rndc_clave', 'rndc_ambiente', 'rndc_conectado', 'carga_rol', 'carga_cobro_modo', 'carga_cobro_valor', 'transito_habilitado', 'transito_precios', 'transito_tipos', 'transito_auto', 'transito_auto_tipos', 'transito_frecuencia_dias',
        'transito_tope_mensual', 'transito_bloquear_licencia', 'transito_bloquear_comparendos', 'transito_bloquear_desde', 'webhook_activo', 'webhook_url', 'webhook_secreto', 'webhook_eventos',
    ];

    /** Credenciales: nunca se envían al navegador ni a la auditoría. */
    protected $hidden = ['onurix_cliente', 'onurix_clave', 'webhook_url', 'webhook_secreto', 'rndc_usuario', 'rndc_clave'];

    protected array $noAuditar = ['onurix_cliente', 'onurix_clave', 'webhook_url', 'webhook_secreto', 'rndc_usuario', 'rndc_clave'];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'modulos' => 'array',
            'rndc_usuario' => 'encrypted',
            'rndc_clave' => 'encrypted',
            'rndc_conectado' => 'boolean',
            'carga_cobro_valor' => 'decimal:2',
            'transito_habilitado' => 'boolean',
            'transito_precios' => 'array',
            'transito_tipos' => 'array',
            'transito_auto' => 'boolean',
            'transito_auto_tipos' => 'array',
            'transito_frecuencia_dias' => 'integer',
            'transito_tope_mensual' => 'decimal:2',
            'transito_bloquear_licencia' => 'boolean',
            'transito_bloquear_comparendos' => 'boolean',
            'transito_bloquear_desde' => 'decimal:2',
            'dv' => 'integer',
            'anio_habilitacion' => 'integer',
            'cotizacion_validez_dias' => 'integer',
            'cotizacion_incluye' => 'array',
            'cotizacion_equipo' => 'array',
            'cotizacion_certificados' => 'array',
            'sms_habilitado' => 'boolean',
            'onurix_cliente' => 'encrypted',
            'onurix_clave' => 'encrypted',
            'sms_limite_mensual' => 'integer',
            'sms_precio' => 'integer',
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
    /** La empresa tiene SMS disponible: la plataforma lo tiene activo y a ella se lo activaron. */
    public function smsConfigurado(): bool
    {
        return $this->sms_habilitado && ProveedorSms::actual()->configurado();
    }

    /** Configuración del diseño de la cotización, con valores por defecto si la empresa no los definió. */
    public function cotizacionConfig(): array
    {
        return [
            'color' => $this->cotizacion_color ?: '#15312a',
            'color_acento' => $this->cotizacion_color_acento ?: '#74a92c',
            'validez_dias' => $this->cotizacion_validez_dias ?: 15,
            'incluye' => $this->cotizacion_incluye ?: ['Combustible', 'Peajes', 'Nómina y seguridad social', 'Viáticos del conductor'],
            'equipo' => $this->cotizacion_equipo ?: ['Tarjeta de propiedad y SOAT al día', 'Revisión técnico-mecánica vigente', 'Póliza de responsabilidad civil', 'Conductores certificados y asegurados'],
            'telefono_2' => $this->cotizacion_telefono_2,
            'instagram' => $this->cotizacion_instagram,
            'certificados' => $this->cotizacion_certificados ?: [],
            'sello_path' => $this->cotizacion_sello_path,
        ];
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
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
        static::creating(function (Empresa $empresa): void {
            $empresa->modulos ??= ModuloEmpresa::porDefecto();
        });
        static::saved(fn (Empresa $empresa) => Modulos::olvidar($empresa->id));
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

    // Consultas de tránsito

    /** Precio que se le cobra a la empresa por una consulta de ese tipo (especial o el de la plataforma). */
    public function tercerosCarga(): HasMany
    {
        return $this->hasMany(TerceroCarga::class);
    }

    public function remolques(): HasMany
    {
        return $this->hasMany(Remolque::class);
    }

    public function registrosRndc(): HasMany
    {
        return $this->hasMany(RegistroRndc::class);
    }

    public function remesasCarga(): HasMany
    {
        return $this->hasMany(RemesaCarga::class);
    }

    public function manifiestosCarga(): HasMany
    {
        return $this->hasMany(ManifiestoCarga::class);
    }

    public function tarifasSicetac(): HasMany
    {
        return $this->hasMany(TarifaSicetac::class);
    }

    public function cumplidosRemesa(): HasMany
    {
        return $this->hasMany(CumplidoRemesa::class);
    }

    public function cumplidosManifiesto(): HasMany
    {
        return $this->hasMany(CumplidoManifiesto::class);
    }

    public function tieneModulo(ModuloEmpresa $modulo): bool
    {
        return in_array($modulo->value, $this->modulos ?? ModuloEmpresa::porDefecto(), true);
    }

    public function precioTransito(TipoConsultaTransito $tipo): ?float
    {
        $especial = $this->transito_precios[$tipo->value] ?? null;

        return $especial !== null && $especial !== '' ? (float) $especial : ProveedorTransito::actual()->precioDe($tipo);
    }

    /** Tipos de consulta que la empresa decidió usar. */
    public function usaTransito(TipoConsultaTransito $tipo): bool
    {
        return $this->transito_habilitado && in_array($tipo->value, $this->transito_tipos ?? [], true);
    }

    /** Valor cobrado en el mes (consultas que el proveedor cobró). */
    public function gastoTransitoDelMes(?\DateTimeInterface $mes = null): float
    {
        return (float) ConsultaTransito::query()->deEmpresa($this->id)->cobradas()->delMes($mes)->sum('precio');
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
        // Si el archivo no está (p. ej. se perdió en un despliegue sin volumen), se muestran las iniciales y no una imagen rota.
        return $this->logo_path && Storage::disk('public')->exists($this->logo_path) ? Storage::disk('public')->url($this->logo_path) : null;
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
