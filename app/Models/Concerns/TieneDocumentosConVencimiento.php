<?php

namespace App\Models\Concerns;

use App\Enums\Contratos\TipoDocumento;
use App\Enums\EstadoDocumento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Para modelos con documentos que vencen y se renuevan con historial (vehículo, conductor).
 *
 * El modelo debe tener la relación `documentos()` y definir `tiposDocumento()` con el enum de tipos.
 * Vigente = el documento de vencimiento más lejano de cada tipo.
 */
trait TieneDocumentosConVencimiento
{
    /** @return class-string<TipoDocumento&\BackedEnum> */
    abstract public static function tiposDocumento(): string;

    /** @return list<TipoDocumento&\BackedEnum> */
    public static function tiposObligatorios(): array
    {
        return array_values(array_filter(
            (static::tiposDocumento())::cases(),
            fn (TipoDocumento $tipo) => $tipo->obligatorio(),
        ));
    }

    /**
     * Documento vigente de cada tipo, indexado por el valor del tipo.
     * Usa la relación cargada si existe, para no repetir consultas en listados.
     */
    public function documentosVigentes(): Collection
    {
        return $this->documentos
            ->sortByDesc(fn (Model $d) => $d->fecha_vencimiento->format('Ymd').str_pad((string) $d->getKey(), 10, '0', STR_PAD_LEFT))
            ->unique(fn (Model $d) => $d->tipo->value)
            ->keyBy(fn (Model $d) => $d->tipo->value);
    }

    public function documentoVigente(TipoDocumento&\BackedEnum $tipo): ?Model
    {
        return $this->documentosVigentes()->get($tipo->value);
    }

    public function estadoDocumento(TipoDocumento&\BackedEnum $tipo, ?\DateTimeInterface $fecha = null): EstadoDocumento
    {
        return EstadoDocumento::paraVencimiento($this->documentoVigente($tipo)?->fecha_vencimiento, $fecha, $tipo->diasAviso());
    }

    /** Peor estado entre los documentos obligatorios (semáforo general). */
    public function estadoDocumental(?\DateTimeInterface $fecha = null): EstadoDocumento
    {
        return collect(static::tiposObligatorios())
            ->map(fn (TipoDocumento $tipo) => $this->estadoDocumento($tipo, $fecha))
            ->sortByDesc(fn (EstadoDocumento $estado) => $estado->gravedad())
            ->first() ?? EstadoDocumento::Vigente;
    }

    /**
     * Documentos obligatorios vencidos o sin registrar, como mensajes.
     *
     * @return list<string>
     */
    public function bloqueosDocumentales(?\DateTimeInterface $fecha = null): array
    {
        $bloqueos = [];

        foreach (static::tiposObligatorios() as $tipo) {
            $documento = $this->documentoVigente($tipo);
            $estado = EstadoDocumento::paraVencimiento($documento?->fecha_vencimiento, $fecha);

            if ($estado === EstadoDocumento::Faltante) {
                $bloqueos[] = "{$tipo->getLabel()}: sin registrar.";
            } elseif ($estado === EstadoDocumento::Vencido) {
                $bloqueos[] = "{$tipo->getLabel()}: vencido el {$documento->fecha_vencimiento->format('d/m/Y')}.";
            }
        }

        return $bloqueos;
    }

    /**
     * Documentos obligatorios vigentes hoy que vencen antes de $fecha, indexados por tipo
     * (sirve para saber hasta qué fecha puede ir un FUEC).
     *
     * @return Collection<string, Model>
     */
    public function vencenAntesDe(\DateTimeInterface $fecha): Collection
    {
        $tipos = array_map(fn (TipoDocumento $t) => $t->value, static::tiposObligatorios());

        $limite = Carbon::instance($fecha)->startOfDay();

        // filter por clave (no only(): en colecciones de modelos, only() filtra por id).
        return $this->documentosVigentes()
            ->filter(fn (Model $d, string $tipo) => in_array($tipo, $tipos, true) && $d->fecha_vencimiento->lt($limite));
    }

    /** "2 vencidos · 1 por vencer", para listados. */
    public function resumenDocumentos(): ?string
    {
        $conteo = collect(static::tiposObligatorios())
            ->map(fn (TipoDocumento $tipo) => $this->estadoDocumento($tipo))
            ->countBy(fn (EstadoDocumento $e) => $e->value);

        $partes = array_filter([
            ($n = $conteo[EstadoDocumento::Vencido->value] ?? 0) ? "{$n} vencido".($n > 1 ? 's' : '') : null,
            ($n = $conteo[EstadoDocumento::Faltante->value] ?? 0) ? "{$n} sin registrar" : null,
            ($n = $conteo[EstadoDocumento::PorVencer->value] ?? 0) ? "{$n} por vencer" : null,
        ]);

        return $partes ? implode(' · ', $partes) : null;
    }
}
