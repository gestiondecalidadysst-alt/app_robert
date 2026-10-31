<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Support\Contratos\VariablesContrato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versión de la plantilla del contrato de una empresa. Las versiones no se editan:
 * cada cambio crea una nueva (App\Actions\Contratos\GuardarPlantillaContrato).
 * La vigente es la de número mayor.
 */
class PlantillaContrato extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'plantillas_contrato';

    protected $fillable = ['empresa_id', 'version', 'codigo_formato', 'titulo', 'contenido', 'notas_cambio', 'user_id'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getVersionFormateadaAttribute(): string
    {
        return str_pad((string) $this->version, 2, '0', STR_PAD_LEFT);
    }

    public function esVigente(): bool
    {
        return ! static::query()->deEmpresa($this->empresa_id)->where('version', '>', $this->version)->exists();
    }

    /** Variables usadas en el contenido. */
    public function variablesUsadas(): array
    {
        return VariablesContrato::usadas($this->contenido);
    }
}
