<?php

namespace App\Models;

use App\Enums\TipoConsultaTransito;
use App\Models\Concerns\PerteneceAEmpresa;
use App\Services\Transito\Interpretar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Una consulta de tránsito: lo que se consultó, el resultado, lo que se cobra y lo que cuesta. */
class ConsultaTransito extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'consultas_transito';

    protected $fillable = [
        'empresa_id', 'tipo', 'consultable_type', 'consultable_id', 'referencia', 'origen', 'user_id', 'estado',
        'creditos', 'cobrada', 'precio', 'costo', 'desde_cache', 'duracion_ms', 'respuesta', 'error', 'codigo_verificacion',
    ];

    protected $hidden = ['respuesta'];

    protected static function booted(): void
    {
        static::creating(function (self $c): void {
            $c->codigo_verificacion ??= Str::lower(Str::random(24));
        });
    }

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
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes();
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
        $mes = Carbon::instance($mes ?? now());

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

    /** Tiene constancia: la consulta respondió (con datos o sin registros). */
    public function tieneConstancia(): bool
    {
        return in_array($this->estado, ['ok', 'sin_datos'], true);
    }

    public function urlVerificacion(): string
    {
        return route('consulta-transito.verificar', ['codigo' => $this->codigo_verificacion]);
    }

    public function urlPdf(): string
    {
        return route('consulta-transito.pdf', ['codigo' => $this->codigo_verificacion]);
    }

    /**
     * Resultado de la consulta tal como se guardó, listo para mostrar:
     * comparendos (lista, pendientes, deuda), licencia (categorías) o RUNT (SOAT y RTM).
     */
    public function detalle(): array
    {
        $json = $this->respuesta ?? [];
        $sinDatos = $this->estado === 'sin_datos';

        return match ($this->tipo) {
            TipoConsultaTransito::ConductorComparendos, TipoConsultaTransito::VehiculoMultas => (function () use ($json, $sinDatos) {
                $d = $sinDatos ? ['comparendos' => [], 'deuda' => 0.0] : Interpretar::comparendos($json);
                $lista = collect($d['comparendos']);
                $pendientes = $lista->whereIn('estado', ['pendiente', 'acuerdo']);

                return [
                    'clase' => 'comparendos',
                    'comparendos' => $lista->sortByDesc('fecha')->values()->all(),
                    'pendientes' => $pendientes->count(),
                    'deuda' => (float) $d['deuda'],
                    'paz_y_salvo' => $pendientes->isEmpty() && (float) $d['deuda'] <= 0,
                ];
            })(),
            TipoConsultaTransito::ConductorLicencia => ['clase' => 'licencia'] + ($sinDatos
                ? ['estado' => null, 'suspendida' => false, 'categorias' => [], 'tiene_multas' => null, 'sin_licencia' => true]
                : Interpretar::licencia($json) + ['sin_licencia' => false]),
            TipoConsultaTransito::VehiculoRunt => ['clase' => 'runt'] + ($sinDatos
                ? ['soat_vence' => null, 'soat_estado' => null, 'rtm_vence' => null, 'rtm_vigente' => null, 'estado_vehiculo' => null, 'sin_registro' => true]
                : Interpretar::runt($json) + ['sin_registro' => false]),
        };
    }
}
