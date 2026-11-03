<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;

class PreoperacionalEstado extends Grafica
{
    protected static ?int $sort = 12;

    protected ?string $heading = 'Preoperacional en días con extracto';

    public function getDescription(): ?string
    {
        return $this->indicadores()->etiquetaPeriodo() . ' (hasta hoy).';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $d = $this->seccion('pesv')['preop_detalle'];

        return [
            'labels' => ["Apto ({$d['aptos']})", "No apto ({$d['no_aptos']})", "Sin hacer ({$d['sin_hacer']})"],
            'datasets' => [Paleta::serieDona([$d['aptos'], $d['no_aptos'], $d['sin_hacer']], [Paleta::BIEN, Paleta::CRITICO, Paleta::ATENCION])],
        ];
    }

    protected function getOptions(): array
    {
        return Paleta::dona();
    }
}
