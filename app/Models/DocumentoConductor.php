<?php

namespace App\Models;

use App\Enums\TipoDocumentoConductor;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\EsDocumentoConVencimiento;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Documento de un conductor (licencia, seguridad social, examen médico…). Cada renovación es
 * un registro nuevo; el vigente de cada tipo es el de vencimiento más lejano.
 */
class DocumentoConductor extends Model
{
    use Auditable, EsDocumentoConVencimiento, PerteneceAEmpresa, SoftDeletes;

    public const DISCO = 'local';

    public const COLUMNA_DUENO = 'conductor_id';

    protected $table = 'documentos_conductor';

    protected $fillable = [
        'empresa_id', 'conductor_id', 'tipo', 'numero', 'categoria_licencia_id', 'entidad', 'fecha_expedicion',
        'fecha_vencimiento', 'archivo_path', 'archivo_nombre', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoConductor::class,
            'fecha_expedicion' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DocumentoConductor $documento): void {
            $documento->empresa_id ??= $documento->conductor?->empresa_id;
        });

        static::saving(function (DocumentoConductor $documento): void {
            if ($documento->tipo !== TipoDocumentoConductor::Licencia) {
                $documento->categoria_licencia_id = null;
            }
        });
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    public function categoriaLicencia(): BelongsTo
    {
        return $this->belongsTo(CategoriaLicencia::class);
    }

    public function emisor(): ?string
    {
        return $this->entidad;
    }
}
