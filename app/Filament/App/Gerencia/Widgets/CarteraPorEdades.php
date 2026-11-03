<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Support\Graficas\Paleta;
use Filament\Support\RawJs;

class CarteraPorEdades extends Grafica
{
    protected static ?int $sort = 18;

    protected ?string $heading = 'Cartera por edades (a hoy)';

    protected ?string $description = 'Días vencidos desde la fecha límite de pago (o el fin del contrato).';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $e = $this->seccion('cartera')['edades'];

        return [
            'labels' => ['Sin vencer', '1–30 días', '31–60 días', '61–90 días', 'Más de 90'],
            'datasets' => [Paleta::serieBarras('Saldo', array_values($e), [Paleta::BIEN, Paleta::ATENCION, Paleta::SERIO, Paleta::CRITICO, Paleta::MUY_CRITICO])],
        ];
    }

    protected function getOptions(): RawJs
    {
        return Paleta::enPesos(Paleta::barras(leyenda: false));
    }
}
