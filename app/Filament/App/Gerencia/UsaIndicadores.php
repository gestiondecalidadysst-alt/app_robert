<?php

namespace App\Filament\App\Gerencia;

use App\Support\Gerencia\Indicadores;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Widgets del tablero de gerencia: arman los indicadores con los filtros de la página
 * (periodo y oficina). Se cachean por petición para no repetir consultas entre widgets.
 */
trait UsaIndicadores
{
    use InteractsWithPageFilters;

    /** @var array<string, Indicadores> */
    private static array $cache = [];

    /** Sin refresco automático (los datos cambian poco y son consultas pesadas). */
    protected function getPollingInterval(): ?string
    {
        return null;
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('ver_gerencia') ?? false;
    }

    protected function indicadores(): Indicadores
    {
        $f = $this->pageFilters ?? [];
        [$desde, $hasta] = Indicadores::periodo($f['periodo'] ?? 'mes', $f['desde'] ?? null, $f['hasta'] ?? null);
        $empresa = Filament::getTenant()->id;
        $oficina = filled($f['oficina_id'] ?? null) ? (int) $f['oficina_id'] : null;
        $clave = implode('|', [$empresa, $desde->toDateString(), $hasta->toDateString(), $oficina]);

        return self::$cache[$clave] ??= new Indicadores($empresa, $desde, $hasta, $oficina);
    }

    /** Sección calculada una sola vez por petición. */
    protected function seccion(string $nombre): array
    {
        static $secciones = [];
        $i = $this->indicadores();
        $clave = spl_object_id($i).$nombre;

        return $secciones[$clave] ??= $i->{$nombre}();
    }
}
