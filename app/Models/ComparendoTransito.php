<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Comparendo o multa de un conductor (por cédula) o de un vehículo (por placa), tal como lo devolvió
 * la última consulta. Si deja de aparecer, se marca `ya_no_aparece_at` (pagado o depurado).
 */
class ComparendoTransito extends Model
{
    use PerteneceAEmpresa;

    public const ESTADOS = ['pendiente' => 'Pendiente', 'acuerdo' => 'Acuerdo de pago', 'pagada' => 'Pagado', 'otro' => 'Otro'];

    protected $table = 'comparendos_transito';

    protected $fillable = [
        'empresa_id', 'consultable_type', 'consultable_id', 'numero', 'fecha', 'codigo', 'infraccion', 'valor', 'estado',
        'tipo', 'organismo', 'placa', 'primera_vez_at', 'ultima_vez_at', 'ya_no_aparece_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valor' => 'decimal:2',
            'primera_vez_at' => 'datetime',
            'ultima_vez_at' => 'datetime',
            'ya_no_aparece_at' => 'datetime',
        ];
    }

    public function consultable(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    /** Los que salieron en la última consulta. */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('ya_no_aparece_at'));
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->vigentes()->where($query->qualifyColumn('estado'), 'pendiente');
    }

    public function etiquetaEstado(): string
    {
        return $this->ya_no_aparece_at ? 'Ya no aparece' : (self::ESTADOS[$this->estado] ?? $this->estado);
    }
}
