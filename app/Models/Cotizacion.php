<?php

namespace App\Models;

use App\Enums\EstadoCotizacion;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cotización de servicios de transporte especial. Dirigida a un cliente o a un prospecto (aún no
 * cliente). Lleva líneas Fecha/Ruta/Duración/Valor, descuento e IVA opcional; genera un PDF con la
 * marca de la empresa y, al aceptarse, se puede convertir en contrato.
 */
class Cotizacion extends Model
{
    use Auditable;
    use PerteneceAEmpresa;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'empresa_id', 'numero', 'cliente_id', 'prospecto_nombre', 'prospecto_contacto',
        'prospecto_telefono', 'prospecto_email', 'ciudad', 'fecha', 'valido_hasta', 'asunto',
        'descuento', 'aplica_iva', 'iva_porcentaje', 'estado', 'observaciones', 'user_id',
        'contrato_id', 'enviada_at', 'anulado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valido_hasta' => 'date',
            'descuento' => 'integer',
            'aplica_iva' => 'boolean',
            'iva_porcentaje' => 'integer',
            'numero' => 'integer',
            'estado' => EstadoCotizacion::class,
            'enviada_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    // Relaciones

    public function items(): HasMany
    {
        return $this->hasMany(CotizacionItem::class)->orderBy('orden')->orderBy('id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes();
    }

    // Totales

    public function subtotal(): int
    {
        return (int) $this->items->sum('valor');
    }

    public function baseGravable(): int
    {
        return max(0, $this->subtotal() - (int) $this->descuento);
    }

    public function iva(): int
    {
        return $this->aplica_iva ? (int) round($this->baseGravable() * $this->iva_porcentaje / 100) : 0;
    }

    public function total(): int
    {
        return $this->baseGravable() + $this->iva();
    }

    // Estado

    public function anulada(): bool
    {
        return $this->anulado_at !== null;
    }

    /** Vencida: en borrador o enviada y ya pasó la validez. */
    public function vencida(): bool
    {
        return in_array($this->estado, [EstadoCotizacion::Borrador, EstadoCotizacion::Enviada], true)
            && $this->valido_hasta?->isPast()
            && ! $this->valido_hasta->isToday();
    }

    public function convertida(): bool
    {
        return $this->contrato_id !== null;
    }

    /** Etiqueta de estado para mostrar (incluye "Vencida", que no es un estado guardado). */
    public function etiquetaEstado(): string
    {
        if ($this->anulada()) {
            return 'Anulada';
        }

        return $this->vencida() ? 'Vencida' : $this->estado->getLabel();
    }

    // Destinatario (cliente o prospecto)

    public function destinatarioNombre(): string
    {
        return $this->cliente?->nombre ?: ($this->prospecto_nombre ?: 'Señores');
    }

    public function destinatarioCorreo(): ?string
    {
        return $this->cliente?->email ?: $this->prospecto_email;
    }

    // Número

    public function getNumeroFormateadoAttribute(): string
    {
        return 'COT-'.$this->fecha->format('Y').'-'.str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT);
    }
}
