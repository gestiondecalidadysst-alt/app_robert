<?php

namespace App\Models;

use App\Enums\EstadoServicio;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Support\Operacion\Horario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Servicio del planeador (PESV paso 15): el plan de un desplazamiento.
 * - Se planifica antes del FUEC (y desde aquí se genera el extracto) o nace al expedir un FUEC.
 * - Un FUEC = un servicio. Si el FUEC se anula, el servicio queda cancelado.
 * - El seguimiento del conductor (leído, inicio, paradas, novedades, fin) es opcional.
 */
class Servicio extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'servicios';

    protected $fillable = [
        'empresa_id', 'contrato_id', 'fuec_id', 'ruta_id', 'vehiculo_id', 'fecha_inicio', 'fecha_fin', 'hora_salida',
        'hora_llegada', 'origen_municipio_id', 'destino_municipio_id', 'recorrido', 'conduccion_min', 'pasajeros',
        'observaciones', 'estado', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'estado' => EstadoServicio::class,
            'conduccion_min' => 'integer',
            'pasajeros' => 'integer',
            'leido_at' => 'datetime',
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
            'km_inicial' => 'integer',
            'km_final' => 'integer',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function fuec(): BelongsTo
    {
        return $this->belongsTo(Fuec::class);
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'origen_municipio_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'destino_municipio_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function leidoPor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class, 'leido_por')->withTrashed();
    }

    public function conductores(): BelongsToMany
    {
        return $this->belongsToMany(Conductor::class, 'servicio_conductor')
            ->withPivot('orden')->orderByPivot('orden')->withTrashed();
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoServicio::class)->orderBy('created_at')->orderBy('id');
    }

    public function cancelado(): bool
    {
        return $this->estado === EstadoServicio::Cancelado;
    }

    public function horario(): Horario
    {
        return new Horario($this->fecha_inicio, $this->fecha_fin, $this->hora_salida, $this->hora_llegada);
    }

    /** Fecha y hora de la primera salida. */
    public function salida(): Carbon
    {
        return $this->fecha_inicio->copy()->setTimeFromTimeString($this->hora_salida ?: '00:00');
    }

    /** Horas entre la planificación y la salida (negativo si se planificó después de salir). */
    public function horasDeAntelacion(): float
    {
        return round(($this->salida()->getTimestamp() - ($this->created_at ?? now())->getTimestamp()) / 3600, 1);
    }

    public function planificadoConAntelacion(int $horas): bool
    {
        return $this->horasDeAntelacion() >= $horas;
    }

    /** Conducción estimada por día: la del servicio, la de su ruta o la franja horaria. */
    public function minutosConduccion(): ?int
    {
        if ($this->conduccion_min) {
            return $this->conduccion_min;
        }
        if ($this->ruta?->duracion_min) {
            return $this->ruta->duracion_min;
        }
        if ($this->hora_salida && $this->hora_llegada) {
            $a = Carbon::parse($this->hora_salida);
            $b = Carbon::parse($this->hora_llegada);
            $min = (int) $a->diffInMinutes($b, false);

            return $min > 0 ? $min : $min + 1440;
        }

        return null;
    }

    public function activoEl(Carbon $dia): bool
    {
        return $dia->betweenIncluded($this->fecha_inicio->copy()->startOfDay(), $this->fecha_fin->copy()->endOfDay());
    }

    public function getRutaTextoAttribute(): string
    {
        return ($this->origen?->nombre ?? '?') . ' → ' . ($this->destino?->nombre ?? '?');
    }

    public function getPlacaAttribute(): ?string
    {
        return $this->vehiculo?->placa;
    }

    /** "06:00 – 15:00" o "Todo el día" */
    public function getFranjaAttribute(): string
    {
        if (! $this->hora_salida && ! $this->hora_llegada) {
            return 'Todo el día';
        }

        return substr((string) $this->hora_salida, 0, 5) . ' – ' . ($this->hora_llegada ? substr((string) $this->hora_llegada, 0, 5) : '…');
    }

    public function registrar(string $tipo, array $datos = []): EventoServicio
    {
        return $this->eventos()->create([
            'empresa_id' => $this->empresa_id,
            'tipo' => $tipo,
            'conductor_id' => $datos['conductor_id'] ?? null,
            'user_id' => $datos['user_id'] ?? auth()->id(),
            'nota' => $datos['nota'] ?? null,
            'kilometraje' => $datos['kilometraje'] ?? null,
            'latitud' => $datos['latitud'] ?? null,
            'longitud' => $datos['longitud'] ?? null,
        ]);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), '!=', EstadoServicio::Cancelado->value);
    }

    /** Servicios que tocan el rango de fechas. */
    public function scopeEntre(Builder $query, Carbon $desde, Carbon $hasta): Builder
    {
        return $query->whereDate($query->qualifyColumn('fecha_inicio'), '<=', $hasta->toDateString())
            ->whereDate($query->qualifyColumn('fecha_fin'), '>=', $desde->toDateString());
    }
}
