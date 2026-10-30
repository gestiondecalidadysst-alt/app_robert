<?php

namespace App\Models\Concerns;

use App\Enums\EstadoDocumento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Para los registros de documentos con vencimiento (DocumentoVehiculo, DocumentoConductor).
 * El modelo define la constante COLUMNA_DUENO (vehiculo_id, conductor_id) y DISCO.
 */
trait EsDocumentoConVencimiento
{
    public function estado(?\DateTimeInterface $hoy = null): EstadoDocumento
    {
        return EstadoDocumento::paraVencimiento($this->fecha_vencimiento, $hoy, $this->tipo?->diasAviso());
    }

    /** Días que faltan para el vencimiento (negativo si ya venció). */
    public function diasParaVencer(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->fecha_vencimiento->copy()->startOfDay(), false);
    }

    public function tieneArchivo(): bool
    {
        return filled($this->archivo_path) && Storage::disk(static::DISCO)->exists($this->archivo_path);
    }

    /** Enlace firmado temporal para abrir el archivo (disco privado). */
    public function urlArchivo(): ?string
    {
        return $this->tieneArchivo()
            ? Storage::disk(static::DISCO)->temporaryUrl($this->archivo_path, now()->addMinutes(10))
            : null;
    }

    /** Vencidos (fecha anterior a hoy). */
    public function scopeVencidos(Builder $query): Builder
    {
        return $query->whereDate($query->qualifyColumn('fecha_vencimiento'), '<', today());
    }

    /** Por vencer: desde hoy hasta los días de aviso de cada tipo de documento. */
    public function scopePorVencer(Builder $query): Builder
    {
        return $query->whereDate($query->qualifyColumn('fecha_vencimiento'), '>=', today())
            ->where(fn (Builder $q) => $this->dentroDelAviso($q));
    }

    /** Vencidos o por vencer: lo que requiere atención. */
    public function scopeEnAtencion(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $this->dentroDelAviso($q));
    }

    /** Vigentes con holgura (fuera del periodo de aviso). */
    public function scopeAlDia(Builder $query): Builder
    {
        return $query->whereNot(fn (Builder $q) => $this->dentroDelAviso($q));
    }

    /** fecha_vencimiento <= hoy + días de aviso, según el tipo de cada documento. */
    private function dentroDelAviso(Builder $query): void
    {
        $columna = $query->qualifyColumn('fecha_vencimiento');
        $enum = $this->getCasts()['tipo'];

        foreach ($enum::cases() as $tipo) {
            $dias = $tipo->diasAviso() ?? EstadoDocumento::diasAvisoPorDefecto();
            $query->orWhere(fn (Builder $q) => $q
                ->where($q->qualifyColumn('tipo'), $tipo->value)
                ->whereDate($columna, '<=', today()->addDays($dias)));
        }
    }

    /**
     * Solo el documento vigente de cada tipo por dueño (sin el historial).
     * Vigente = el de vencimiento más lejano; ante empate, el registrado de último.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        $tabla = $this->getTable();
        $dueno = static::COLUMNA_DUENO;

        return $query->whereNotExists(function ($sub) use ($tabla, $dueno) {
            $sub->selectRaw('1')
                ->from("{$tabla} as posterior")
                ->whereColumn("posterior.{$dueno}", "{$tabla}.{$dueno}")
                ->whereColumn('posterior.tipo', "{$tabla}.tipo")
                ->whereNull('posterior.deleted_at')
                ->where(fn ($q) => $q
                    ->whereColumn('posterior.fecha_vencimiento', '>', "{$tabla}.fecha_vencimiento")
                    ->orWhere(fn ($q) => $q
                        ->whereColumn('posterior.fecha_vencimiento', "{$tabla}.fecha_vencimiento")
                        ->whereColumn('posterior.id', '>', "{$tabla}.id")));
        });
    }
}
