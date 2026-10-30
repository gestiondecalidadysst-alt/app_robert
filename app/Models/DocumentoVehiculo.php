<?php

namespace App\Models;

use App\Enums\TipoDocumentoVehiculo;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\EsDocumentoConVencimiento;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Documento de un vehículo (SOAT, técnico-mecánica, pólizas…). Cada renovación es un
 * registro nuevo; el vigente de cada tipo es el de vencimiento más lejano.
 */
class DocumentoVehiculo extends Model
{
    use Auditable, EsDocumentoConVencimiento, PerteneceAEmpresa, SoftDeletes;

    /** Disco privado: los archivos solo se abren desde la app, con enlace firmado. */
    public const DISCO = 'local';

    public const COLUMNA_DUENO = 'vehiculo_id';

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

    public function emisor(): ?string
    {
        return $this->aseguradora?->nombre ?? $this->entidad;
    }
}
