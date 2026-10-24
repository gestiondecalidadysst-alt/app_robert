<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Support\Nit;
use Illuminate\Database\Eloquent\Model;

/**
 * Empresa con la que se tiene convenio empresarial o vinculación de flota.
 */
class EmpresaConvenio extends Model
{
    use Auditable, PerteneceAEmpresa;

    public const TIPOS_VINCULACION = [
        'convenio_empresarial' => 'Convenio empresarial',
        'vinculacion_flota' => 'Vinculación de flota',
    ];

    protected $table = 'empresas_convenio';

    protected $fillable = ['empresa_id', 'nit', 'dv', 'razon_social', 'tipo_vinculacion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'dv' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (EmpresaConvenio $convenio): void {
            $convenio->nit = Nit::limpiar($convenio->nit);
            $convenio->dv = Nit::digitoVerificacion($convenio->nit);
        });
    }
}
