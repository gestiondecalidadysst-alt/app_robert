<?php

namespace App\Models;

use App\Enums\TipoPuntoRuta;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Rutograma: una ruta frecuente de la empresa, reutilizable en cada servicio con el mismo origen y
 * destino (y al revés si es de ida y vuelta). PESV paso 15: rutas con puntos críticos, paradas
 * seguras, velocidades y rutas alternas.
 */
class Ruta extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'rutas';

    protected $fillable = [
        'empresa_id', 'nombre', 'origen_municipio_id', 'destino_municipio_id', 'ida_y_vuelta', 'recorrido',
        'distancia_km', 'duracion_min', 'velocidad_maxima', 'puntos', 'rutas_alternas', 'recomendaciones',
        'contactos_emergencia', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'ida_y_vuelta' => 'boolean',
            'activo' => 'boolean',
            'puntos' => 'array',
            'distancia_km' => 'integer',
            'duracion_min' => 'integer',
            'velocidad_maxima' => 'integer',
        ];
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'origen_municipio_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'destino_municipio_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('activo'), true);
    }

    /** Puntos de un tipo (paradas, críticos…), en el orden del recorrido. */
    public function puntosDe(?TipoPuntoRuta $tipo = null): Collection
    {
        return collect($this->puntos ?? [])
            ->filter(fn ($p) => filled($p['nombre'] ?? null))
            ->when($tipo, fn ($c) => $c->where('tipo', $tipo->value))
            ->values();
    }

    public function tieneParadas(): bool
    {
        return $this->puntosDe(TipoPuntoRuta::Parada)->isNotEmpty();
    }

    /** "3 h 20 min" */
    public static function duracionTexto(?int $minutos): string
    {
        if (! $minutos) {
            return '—';
        }

        return trim((intdiv($minutos, 60) ? intdiv($minutos, 60).' h ' : '').($minutos % 60 ? ($minutos % 60).' min' : ''));
    }

    /**
     * Rutograma que corresponde a un origen y destino: el exacto o, si es de ida y vuelta, el inverso.
     */
    public static function sugerida(int $empresaId, ?int $origen, ?int $destino): ?self
    {
        if (! $origen || ! $destino) {
            return null;
        }

        $rutas = self::query()->deEmpresa($empresaId)->activas()
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('origen_municipio_id', $origen)->where('destino_municipio_id', $destino))
                ->orWhere(fn ($q) => $q->where('origen_municipio_id', $destino)->where('destino_municipio_id', $origen)->where('ida_y_vuelta', true)))
            ->orderBy('id')->get();

        return $rutas->first(fn (self $r) => $r->origen_municipio_id === $origen) ?? $rutas->first();
    }
}
