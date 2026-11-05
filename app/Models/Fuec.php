<?php

namespace App\Models;

use App\Enums\EstadoVigencia;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Support\Operacion\Horario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * FUEC – Formato Único de Extracto del Contrato (Resolución 6652 de 2019).
 *
 * Se expide solo con App\Actions\Operacion\ExpedirFuec. `datos` guarda una copia de la empresa,
 * contrato, vehículo y conductores al momento de expedir: el PDF siempre se genera con esa copia.
 * No se edita ni se borra: se anula.
 */
class Fuec extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'fuecs';

    protected $fillable = [
        'empresa_id', 'contrato_id', 'consecutivo', 'numero', 'vehiculo_id', 'fecha_inicio', 'fecha_fin', 'hora_inicio', 'hora_fin',
        'origen_municipio_id', 'destino_municipio_id', 'recorrido', 'empresa_convenio_id', 'datos',
        'codigo_verificacion', 'oficina_id', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'datos' => 'array',
            'anulado_at' => 'datetime',
            'consecutivo' => 'integer',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    public function conductores(): BelongsToMany
    {
        return $this->belongsToMany(Conductor::class, 'fuec_conductor')
            ->withPivot('orden')
            ->orderByPivot('orden')
            ->withTrashed();
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'origen_municipio_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'destino_municipio_id');
    }

    public function empresaConvenio(): BelongsTo
    {
        return $this->belongsTo(EmpresaConvenio::class);
    }

    public function oficina(): BelongsTo
    {
        return $this->belongsTo(Oficina::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function anulado(): bool
    {
        return $this->anulado_at !== null;
    }

    public function estado(?\DateTimeInterface $hoy = null): EstadoVigencia
    {
        return EstadoVigencia::para($this->fecha_inicio, $this->fecha_fin, $this->anulado(), $hoy);
    }

    /** "425 0123 15 2026 0045 0003" para mostrar (el número legal son los 21 dígitos seguidos). */
    public function getNumeroFormateadoAttribute(): string
    {
        $n = $this->numero;

        return implode(' ', [substr($n, 0, 3), substr($n, 3, 4), substr($n, 7, 2), substr($n, 9, 4), substr($n, 13, 4), substr($n, 17, 4)]);
    }

    public function horario(): Horario
    {
        return new Horario($this->fecha_inicio, $this->fecha_fin, $this->hora_inicio, $this->hora_fin);
    }

    public function getPlacaAttribute(): ?string
    {
        return $this->datos['vehiculo']['placa'] ?? $this->vehiculo?->placa;
    }

    public function urlVerificacion(): string
    {
        return route('fuec.verificar', ['codigo' => $this->codigo_verificacion]);
    }

    public function scopeNoAnulados(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('anulado_at'));
    }
}
