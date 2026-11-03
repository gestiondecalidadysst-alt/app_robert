<?php

namespace App\Models;

use App\Enums\EstadoActividad;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Actividad del programa anual: una rutina de un vehículo con fecha y km programados.
 * `fecha_original` / `km_original` son la línea base (no cambian); cada reprogramación, ajuste,
 * ejecución o cancelación queda en `historial` (quién, cuándo, de qué a qué y por qué).
 */
class ActividadMantenimiento extends Model
{
    use PerteneceAEmpresa;

    public const ESTADO_VENCIDA = 'vencida';

    public const ESTADO_PROXIMA = 'proxima';

    protected $table = 'actividades_mantenimiento';

    protected $fillable = [
        'empresa_id', 'programa_mantenimiento_id', 'vehiculo_id', 'rutina_mantenimiento_id', 'rutina_nombre', 'frecuencia',
        'fecha_original', 'km_original', 'fecha_programada', 'km_programado', 'estado', 'incorporada',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoActividad::class,
            'fecha_original' => 'date',
            'fecha_programada' => 'date',
            'fecha_ejecucion' => 'date',
            'km_original' => 'integer',
            'km_programado' => 'integer',
            'km_ejecucion' => 'integer',
            'incorporada' => 'boolean',
            'historial' => 'array',
            'avisado_proximo_at' => 'datetime',
            'avisado_vencido_at' => 'datetime',
        ];
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(ProgramaMantenimiento::class, 'programa_mantenimiento_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    public function rutina(): BelongsTo
    {
        return $this->belongsTo(RutinaMantenimiento::class, 'rutina_mantenimiento_id');
    }

    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Mantenimiento::class);
    }

    /** Estado para mostrar: programada / próxima / vencida / ejecutada / cancelada. */
    public function situacion(int $diasAviso = 15): string
    {
        if ($this->estado !== EstadoActividad::Programada) {
            return $this->estado->value;
        }

        return match (true) {
            $this->fecha_programada->lt(today()) => self::ESTADO_VENCIDA,
            $this->fecha_programada->lte(today()->addDays($diasAviso)) => self::ESTADO_PROXIMA,
            default => EstadoActividad::Programada->value,
        };
    }

    public function reprogramada(): bool
    {
        return ! $this->fecha_programada->isSameDay($this->fecha_original);
    }

    /** Días de diferencia entre lo ejecutado y lo programado originalmente (+ = tarde). */
    public function desviacionDias(): ?int
    {
        return $this->fecha_ejecucion ? (int) $this->fecha_original->diffInDays($this->fecha_ejecucion, false) : null;
    }

    public function registrar(string $tipo, string $detalle, ?User $usuario = null): void
    {
        $historial = $this->historial ?? [];
        $historial[] = ['fecha' => now()->toDateTimeString(), 'tipo' => $tipo, 'detalle' => $detalle, 'usuario' => $usuario?->name];
        $this->historial = $historial;
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), EstadoActividad::Programada->value);
    }
}
