<?php

namespace App\Filament\App\Widgets;

use App\Support\Gerencia\Indicadores;
use App\Support\Graficas\Paleta;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** Inicio: recaudo del mes por tipo de pago (abono, abono final, pago total). */
class InicioPagosPorTipo extends ChartWidget
{
    protected static ?int $sort = -25;

    protected ?string $heading = 'Pagos del mes: abonos y pagos totales';

    protected ?string $maxHeight = '260px';

    protected function getPollingInterval(): ?string
    {
        return null;
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('ver_pagos_contrato') ?? false;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $t = (new Indicadores(Filament::getTenant()->id, today()->startOfMonth(), today()->endOfMonth()))->pagosPorTipo();

        return [
            'labels' => ["Abonos ({$t['abono']['n']})", "Abonos finales ({$t['abono_final']['n']})", "Pagos totales ({$t['total']['n']})"],
            'datasets' => [Paleta::serieDona([$t['abono']['valor'], $t['abono_final']['valor'], $t['total']['valor']], [Paleta::AZUL, Paleta::NARANJA, Paleta::AGUA])],
        ];
    }

    protected function getOptions(): RawJs
    {
        return Paleta::enPesos(Paleta::dona());
    }
}
