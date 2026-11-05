<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Enums\ModuloEmpresa;
use App\Support\Graficas\Paleta;
use App\Support\Modulos;
use Filament\Facades\Filament;
use Filament\Support\RawJs;

/** Flete expedido mes a mes: el tamaño del negocio de carga en el último año. */
class TendenciaCarga extends Grafica
{
    protected static ?int $sort = 19;

    protected ?string $heading = 'Carga: flete expedido por mes';

    protected int|string|array $columnSpan = 2;

    public static function canView(): bool
    {
        $empresa = Filament::getTenant();

        return $empresa
            && Modulos::activo($empresa->getKey(), ModuloEmpresa::Carga)
            && (auth()->user()?->can('ver_gerencia') ?? false)
            && (auth()->user()?->can('ver_manifiestos_carga') ?? false);
    }

    public function getDescription(): ?string
    {
        return 'Últimos 12 meses, con el número de manifiestos de cada mes.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $t = $this->seccion('tendenciaCarga');

        return [
            'labels' => $t['etiquetas'],
            'datasets' => [
                Paleta::serieBarras('Flete expedido', $t['flete'], Paleta::NARANJA),
                Paleta::serieLinea('Manifiestos', $t['manifiestos'], Paleta::AZUL) + ['type' => 'line', 'yAxisID' => 'y1'],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        $opciones = Paleta::barras(pesos: true);
        $opciones['scales']['y1'] = [
            'position' => 'right',
            'beginAtZero' => true,
            'grid' => ['drawOnChartArea' => false],
            'ticks' => ['precision' => 0],
        ];

        return Paleta::enPesos($opciones);
    }
}
