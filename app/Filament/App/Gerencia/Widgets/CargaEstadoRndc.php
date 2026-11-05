<?php

namespace App\Filament\App\Gerencia\Widgets;

use App\Enums\ModuloEmpresa;
use App\Support\Graficas\Paleta;
use App\Support\Modulos;
use Filament\Facades\Filament;
use Filament\Support\RawJs;

/** Cómo va el reporte al RNDC: lo radicado frente a lo que quedó con errores o en espera. */
class CargaEstadoRndc extends Grafica
{
    protected static ?int $sort = 18;

    protected ?string $heading = 'Carga: estado ante el RNDC';

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
        return 'Manifiestos y remesas del periodo · '.$this->indicadores()->etiquetaPeriodo();
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $r = $this->seccion('carga')['rndc'];

        return [
            'labels' => [
                "Radicados ({$r['registrado']})",
                "Cambiaron tras radicar ({$r['desactualizado']})",
                "En espera por caída ({$r['contingencia']})",
                "Con errores ({$r['error']})",
                "Sin enviar ({$r['pendiente']})",
            ],
            'datasets' => [Paleta::serieDona(
                [$r['registrado'], $r['desactualizado'], $r['contingencia'], $r['error'], $r['pendiente']],
                [Paleta::BIEN, Paleta::ATENCION, Paleta::AZUL, Paleta::CRITICO, '#94a3b8'],
            )],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(json_encode(Paleta::dona()));
    }
}
