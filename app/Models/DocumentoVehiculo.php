<?php

namespace App\Models;

use App\Enums\EstadoDocumento;
use App\Enums\TipoDocumentoVehiculo;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Documento de un vehículo (SOAT, técnico-mecánica, pólizas…). Cada renovación es un
 * registro nuevo; el vigente de cada tipo es el de vencimiento más lejano.
 */
class DocumentoVehiculo extends Model
{
    use Auditable, PerteneceAEmpresa, SoftDeletes;

    /** Disco privado: los archivos solo se descargan desde la app, con permiso. */
    public const DISCO = 'local';

    protected $table = 'documentos_vehiculo';

    protected $fillable = [
        'empresa_id', 'vehiculo_id', 'tipo', 'numero', 'aseguradora_id', 'entidad', 'fecha_expedicion',
        'fecha_vencimiento', 'archivo_path', 'archivo_nombre', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoVehiculo::class,
            'fecha_expedicion' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DocumentoVehiculo $documento): void {
            // Hereda la empresa del vehículo (por ejemplo, al crearlo desde consola o seeders).
            $documento->empresa_id ??= $documento->vehiculo?->empresa_id;
        });
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function aseguradora(): BelongsTo
    {
        return $this->belongsTo(Aseguradora::class);
    }

    public function estado(?\DateTimeInterface $hoy = null): EstadoDocumento
    {
        return EstadoDocumento::paraVencimiento($this->fecha_vencimiento, $hoy);
    }

    /** Días que faltan para el vencimiento (negativo si ya venció). */
    public function diasParaVencer(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->fecha_vencimiento->copy()->startOfDay(), false);
    }

    public function emisor(): ?string
    {
        return $this->aseguradora?->nombre ?? $this->entidad;
    }

    public function tieneArchivo(): bool
    {
        return filled($this->archivo_path) && Storage::disk(self::DISCO)->exists($this->archivo_path);
    }

    /**
     * Solo el documento vigente de cada tipo por vehículo (sin el historial).
     * Vigente = el de vencimiento más lejano; ante empate, el registrado de último.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        $tabla = $this->getTable();

        return $query->whereNotExists(function ($sub) use ($tabla) {
            $sub->selectRaw('1')
                ->from("{$tabla} as posterior")
                ->whereColumn('posterior.vehiculo_id', "{$tabla}.vehiculo_id")
                ->whereColumn('posterior.tipo', "{$tabla}.tipo")
                ->whereNull('posterior.deleted_at')
                ->where(fn ($q) => $q
                    ->whereColumn('posterior.fecha_vencimiento', '>', "{$tabla}.fecha_vencimiento")
                    ->orWhere(fn ($q) => $q
                        ->whereColumn('posterior.fecha_vencimiento', "{$tabla}.fecha_vencimiento")
                        ->whereColumn('posterior.id', '>', "{$tabla}.id")));
        });
    }
}
