<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un pasajero registrado en el listado de un FUEC (Turismo / Grupo específico).
 * Se crea por autorregistro público; el operador los ve y cierra el listado.
 */
class PasajeroFuec extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'pasajeros_fuec';

    protected $fillable = [
        'empresa_id', 'fuec_id', 'orden', 'nombre', 'tipo_documento', 'documento',
        'telefono', 'es_menor', 'acudiente_nombre', 'acudiente_documento', 'registrado_at',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'es_menor' => 'boolean',
            'registrado_at' => 'datetime',
        ];
    }

    public function fuec(): BelongsTo
    {
        return $this->belongsTo(Fuec::class);
    }

    /** Tipos de documento admitidos en el autorregistro. */
    public const TIPOS_DOCUMENTO = [
        'CC' => 'Cédula de ciudadanía',
        'TI' => 'Tarjeta de identidad',
        'CE' => 'Cédula de extranjería',
        'PAS' => 'Pasaporte',
        'RC' => 'Registro civil',
    ];
}
