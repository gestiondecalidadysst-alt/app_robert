<?php

namespace App\Filament\App\Widgets;

use App\Support\Gerencia\Indicadores;
use App\Support\Graficas\Paleta;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** Inicio: recaudo de contratos por día del mes. */
class InicioRecaudoMes extends ChartWidget
{
    protected static ?int $sort = -24;

    protected ?string $maxHeight = '260px';

    protected function getPollingInterval(): ?string
    {
        return null;
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('ver_pagos_contrato') ?? false;
    }

    public function getHeading(): ?string
    {
        return 'Recaudo por día · '.ucfirst(today()->translatedFormat('F Y'));
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $s = (new Indicadores(Filament::getTenant()->id, today()->startOfMonth(), today()->endOfMonth()))->serie();

        return ['labels' => $s['etiquetas'], 'datasets' => [Paleta::serieBarras('Recaudo', $s['recaudo'], Paleta::AZUL)]];
    }

    protected function getOptions(): RawJs
    {
        return Paleta::enPesos(Paleta::barras(leyenda: false));
    }
}
