<?php

namespace App\Support\Graficas;

/**
 * Colores de las gráficas (validados con el verificador de paletas: separación para daltonismo y
 * para visión normal). Las series toman los colores EN ESTE ORDEN, nunca rotados; los estados
 * (bien / atención / serio / crítico) son fijos y siempre van con su etiqueta.
 */
final class Paleta
{
    public const AZUL = '#2a78d6';

    public const NARANJA = '#eb6834';

    public const AGUA = '#1baf7a';

    public const AMARILLO = '#eda100';

    public const MAGENTA = '#e87ba4';

    public const VERDE = '#008300';

    public const VIOLETA = '#6250d6';

    public const SERIES = [self::AZUL, self::NARANJA, self::AGUA, self::AMARILLO, self::MAGENTA, self::VERDE, self::VIOLETA];

    public const BIEN = '#0ca30c';

    public const ATENCION = '#fab219';

    public const SERIO = '#ec835a';

    public const CRITICO = '#d03b3b';

    public const MUY_CRITICO = '#8f1f1f';

    /** Opciones comunes de Chart.js: barras redondeadas, leyenda abajo, cuadrícula suave. */
    public static function barras(bool $horizontal = false, bool $pesos = false, bool $leyenda = true): array
    {
        $ejeValor = ['beginAtZero' => true, 'grid' => ['color' => 'rgba(148,163,184,.18)'], 'border' => ['display' => false],
            'ticks' => ['precision' => 0]];
        $ejeCategoria = ['grid' => ['display' => false], 'border' => ['display' => false]];

        return [
            'indexAxis' => $horizontal ? 'y' : 'x',
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => $leyenda, 'position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8]]],
            'scales' => $horizontal ? ['x' => $ejeValor, 'y' => $ejeCategoria] : ['x' => $ejeCategoria, 'y' => $ejeValor],
        ];
    }

    /**
     * Convierte las opciones a JavaScript para formatear valores en pesos ($ 1.234.567) en el eje y
     * en el recuadro emergente.
     */
    public static function enPesos(array $opciones, bool $horizontal = false): \Filament\Support\RawJs
    {
        $eje = $horizontal ? 'x' : 'y';
        if (isset($opciones['scales'][$eje])) {
            $opciones['scales'][$eje]['ticks']['callback'] = '__PESOS_EJE__';
        }
        $opciones['plugins']['tooltip']['callbacks']['label'] = '__PESOS_TT__';

        // Filament pone las opciones dentro de un atributo HTML: sin comillas dobles (se usan simples y `).
        $js = str_replace('"', "'", json_encode($opciones, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $formato = '(v) => `$ ` + Math.round(Number(v)).toLocaleString(`es-CO`)';
        $js = str_replace("'__PESOS_EJE__'", '(v) => { const n = Number(v); return n >= 1e6 ? `$ ` + (n / 1e6).toLocaleString(`es-CO`) + ` M` : `$ ` + n.toLocaleString(`es-CO`); }', $js);
        $js = str_replace("'__PESOS_TT__'", "(c) => (c.dataset.label ? c.dataset.label + `: ` : (c.label ? c.label + `: ` : ``)) + ({$formato})(c.parsed." . ($horizontal ? 'x' : 'y') . ' ?? c.parsed)', $js);

        return \Filament\Support\RawJs::make($js);
    }

    public static function dona(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '62%',
            'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8, 'padding' => 14]]],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }

    /** Conjunto de barras con el estilo de la casa (extremos redondeados, separación entre barras). */
    public static function serieBarras(string $etiqueta, array $datos, string|array $color): array
    {
        return [
            'label' => $etiqueta,
            'data' => $datos,
            'backgroundColor' => $color,
            'borderRadius' => 4,
            'borderSkipped' => 'start',
            'maxBarThickness' => 28,
            'categoryPercentage' => 0.8,
            'barPercentage' => 0.9,
        ];
    }

    public static function serieLinea(string $etiqueta, array $datos, string $color): array
    {
        return [
            'label' => $etiqueta,
            'data' => $datos,
            'borderColor' => $color,
            'backgroundColor' => $color . '26',
            'fill' => true,
            'tension' => 0.35,
            'borderWidth' => 2,
            'pointRadius' => 4,
            'pointHoverRadius' => 6,
            'pointBackgroundColor' => $color,
            'spanGaps' => true,
        ];
    }

    public static function serieDona(array $datos, array $colores): array
    {
        return ['data' => $datos, 'backgroundColor' => $colores, 'borderColor' => 'rgba(255,255,255,.9)', 'borderWidth' => 2, 'hoverOffset' => 8];
    }
}
