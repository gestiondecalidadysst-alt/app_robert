<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro del desplazamiento: el conductor leyó el plan y, si quiere, reporta inicio, paradas,
 * novedades y fin (con kilometraje y ubicación del celular si la autoriza).
 */
class EventoServicio extends Model
{
    use PerteneceAEmpresa;

    public const TIPOS = [
        'leido' => 'Leyó el plan de viaje',
        'inicio' => 'Inició el viaje',
        'parada' => 'Parada',
        'novedad' => 'Novedad',
        'fin' => 'Finalizó el viaje',
        'cancelado' => 'Servicio cancelado',
    ];

    protected $table = 'eventos_servicio';

    protected $fillable = ['empresa_id', 'servicio_id', 'conductor_id', 'user_id', 'tipo', 'nota', 'kilometraje', 'latitud', 'longitud'];

    protected function casts(): array
    {
        return ['kilometraje' => 'integer', 'latitud' => 'float', 'longitud' => 'float'];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function etiqueta(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
