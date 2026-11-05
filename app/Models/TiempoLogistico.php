<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un momento del viaje de una remesa: llegada, entrada o salida en el cargue o el descargue.
 *
 * Son los «tiempos logísticos» que exige reportar el Ministerio. De aquí salen también
 * las horas del cumplido, para que el conductor no tenga que repetirlas.
 */
class TiempoLogistico extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'tiempos_logisticos';

    public const PUNTOS = ['cargue' => 'Cargue', 'descargue' => 'Descargue'];

    public const EVENTOS = ['llegada' => 'Llegada', 'entrada' => 'Entrada', 'salida' => 'Salida'];

    /** El orden en que pueden ocurrir: no se entra sin haber llegado, ni se sale sin haber entrado. */
    public const ORDEN = ['llegada', 'entrada', 'salida'];

    protected $fillable = [
        'empresa_id', 'remesa_id', 'punto', 'evento', 'ocurrido_at',
        'latitud', 'longitud', 'conductor_id', 'user_id', 'observacion',
    ];

    protected function casts(): array
    {
        return ['ocurrido_at' => 'datetime', 'latitud' => 'decimal:7', 'longitud' => 'decimal:7'];
    }

    public function remesa(): BelongsTo
    {
        return $this->belongsTo(RemesaCarga::class, 'remesa_id');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class)->withoutGlobalScopes();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes();
    }

    public function etiqueta(): string
    {
        return (self::EVENTOS[$this->evento] ?? $this->evento).' en '.mb_strtolower(self::PUNTOS[$this->punto] ?? $this->punto);
    }

    public function tieneUbicacion(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }
}
