<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aislamiento multiempresa para modelos con columna `empresa_id`.
 *
 * - Filtra automáticamente por la empresa activa del panel (si hay una).
 * - Rellena `empresa_id` al crear, si no se indicó.
 *
 * Fuera del panel (consola, colas, seeders) no hay empresa activa: ahí se debe
 * filtrar explícitamente con ->deEmpresa($empresa).
 */
trait PerteneceAEmpresa
{
    public static function bootPerteneceAEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $query): void {
            $empresa = static::empresaActiva();

            if ($empresa) {
                $query->where($query->qualifyColumn('empresa_id'), $empresa->getKey());
            }
        });

        static::creating(function ($model): void {
            if (blank($model->empresa_id) && ($empresa = static::empresaActiva())) {
                $model->empresa_id = $empresa->getKey();
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** Consulta explícita de una empresa concreta: ignora el filtro de la empresa activa (propio y de Filament). */
    public function scopeDeEmpresa(Builder $query, Empresa|int $empresa): Builder
    {
        $query->withoutGlobalScope('empresa');

        foreach (Filament::getPanels() as $panel) {
            if ($panel->hasTenancy()) {
                $query->withoutGlobalScope($panel->getTenancyScopeName());
            }
        }

        return $query->where($query->qualifyColumn('empresa_id'), $empresa instanceof Empresa ? $empresa->getKey() : $empresa);
    }

    protected static function empresaActiva(): ?Empresa
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Empresa ? $tenant : null;
    }
}
