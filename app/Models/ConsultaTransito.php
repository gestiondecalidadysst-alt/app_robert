<?php

namespace App\Models;

use App\Enums\TipoConsultaTransito;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Una consulta de tránsito: lo que se consultó, el resultado, lo que se cobra y lo que cuesta. */
class ConsultaTransito extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'consultas_transito';

    protected $fillable = [
        'empresa_id', 'tipo', 'consultable_type', 'consultable_id', 'referencia', 'origen', 'user_id', 'estado',
        'creditos', 'cobrada', 'precio', 'costo', 'desde_cache', 'duracion_ms', 'respuesta', 'error',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoConsultaTransito::class,
            'cobrada' => 'boolean',
            'desde_cache' => 'boolean',
            'precio' => 'decimal:2',
            'costo' => 'decimal:2',
            'creditos' => 'integer',
            'respuesta' => 'array',
        ];
    }

    public function consultable(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopeCobradas(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('cobrada'), true);
    }

    public function scopeDelMes(Builder $query, ?\DateTimeInterface $mes = null): Builder
    {
        $mes = \Illuminate\Support\Carbon::instance($mes ?? now());

        return $query->whereBetween($query->qualifyColumn('created_at'), [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()]);
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            'ok' => 'Con datos',
            'sin_datos' => 'Sin registros',
            default => 'Error',
        };
    }
}
