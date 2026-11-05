<?php

namespace App\Models;

use App\Enums\EstadoFirma;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Documento del contrato emitido: texto diligenciado y congelado a partir de una versión de la
 * plantilla, con su proceso de firma electrónica. No se edita: si hay que cambiar algo se anula
 * y se emite otro.
 *
 * Se crea con App\Actions\Contratos\EmitirDocumentoContrato.
 */
class DocumentoContrato extends Model
{
    use Auditable, PerteneceAEmpresa;

    public const DISCO = 'local';

    protected $table = 'documentos_contrato';

    protected $fillable = [
        'empresa_id', 'contrato_id', 'plantilla_contrato_id', 'version_plantilla', 'codigo_formato', 'contenido',
        'datos', 'huella_contenido', 'firma_contratista_path', 'estado', 'codigo_verificacion', 'user_id',
    ];

    /** Nunca a la auditoría ni al navegador: huella y copia cifrada del enlace. */
    protected $hidden = ['token_hash', 'token_cifrado'];

    protected array $noAuditar = ['token_hash', 'token_cifrado', 'contenido', 'datos'];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'estado' => EstadoFirma::class,
            'token_cifrado' => 'encrypted',
            'token_expira_at' => 'datetime',
            'enviado_at' => 'datetime',
            'visto_at' => 'datetime',
            'firmado_at' => 'datetime',
            'recordatorio_enviado_at' => 'datetime',
            'vencimiento_avisado_at' => 'datetime',
            'anulado_at' => 'datetime',
            'version_plantilla' => 'integer',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaContrato::class, 'plantilla_contrato_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoDocumentoContrato::class)->orderBy('created_at')->orderBy('id');
    }

    public function codigos(): HasMany
    {
        return $this->hasMany(CodigoFirma::class);
    }

    public function envios(): HasMany
    {
        return $this->hasMany(EnvioMensaje::class)->latest('id');
    }

    // Estado

    /** Estado para mostrar: "Vencido" si el enlace venció sin firma. */
    public function estadoFirma(): EstadoFirma
    {
        if (in_array($this->estado, [EstadoFirma::Enviado, EstadoFirma::Visto], true)
            && $this->token_expira_at?->isPast()) {
            return EstadoFirma::Vencido;
        }

        return $this->estado;
    }

    public function anulado(): bool
    {
        return $this->estado === EstadoFirma::Anulado;
    }

    public function firmado(): bool
    {
        return $this->estado === EstadoFirma::Firmado;
    }

    /** Se puede firmar con el enlace: enviado o visto, con enlace vigente. */
    public function admiteFirma(): bool
    {
        return in_array($this->estado, [EstadoFirma::Enviado, EstadoFirma::Visto], true)
            && $this->token_expira_at?->isFuture();
    }

    // Enlace de firma

    public static function huellaToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function porToken(string $token): ?self
    {
        return static::query()->withoutGlobalScopes()->where('token_hash', self::huellaToken($token))->first();
    }

    /** Enlace público vigente (null si no se ha enviado o fue anulado). */
    public function enlaceFirma(): ?string
    {
        return $this->token_cifrado ? route('contrato.firma', ['token' => $this->token_cifrado]) : null;
    }

    public function urlVerificacion(): string
    {
        return route('contrato.verificar', ['codigo' => $this->codigo_verificacion]);
    }

    // Archivos

    public function rutaPdfFirmado(): ?string
    {
        return $this->pdf_firmado_path && Storage::disk(self::DISCO)->exists($this->pdf_firmado_path)
            ? Storage::disk(self::DISCO)->path($this->pdf_firmado_path)
            : null;
    }

    public function nombreArchivo(): string
    {
        $numero = $this->datos['valores']['contrato.numero'] ?? $this->contrato_id;

        return 'Contrato-'.$numero.($this->firmado() ? '-firmado' : '').'.pdf';
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), '!=', EstadoFirma::Anulado->value);
    }

    /** Registra un evento de evidencia. */
    public function registrar(string $evento, array $detalle = [], ?string $ip = null, ?string $userAgent = null): EventoDocumentoContrato
    {
        return $this->eventos()->create([
            'evento' => $evento,
            'detalle' => $detalle ?: null,
            'ip' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            'user_id' => auth()->id(),
        ]);
    }
}
