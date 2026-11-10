<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ConsultaTransito;
use App\Models\Empresa;
use App\Models\EnvioMensaje;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Encabezado de resumen de una empresa en su ficha: estado, usuarios, módulos activos,
 * último acceso y consumo del mes, antes de entrar al formulario de edición.
 */
class ResumenEmpresa extends StatsOverviewWidget
{
    public ?Empresa $record = null;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected function getStats(): array
    {
        $empresa = $this->record;

        if (! $empresa) {
            return [];
        }

        $inicio = Carbon::now()->startOfMonth();
        $fin = Carbon::now()->endOfMonth();

        $usuarios = $empresa->usuarios()->count();
        $modulos = count($empresa->modulos ?? []);
        $ultimoAcceso = $empresa->usuarios()->max('ultimo_acceso_at');

        $sms = (int) EnvioMensaje::query()->withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)->where('canal', 'sms')->where('estado', 'enviado')
            ->whereBetween('created_at', [$inicio, $fin])->sum('segmentos');

        $consultas = ConsultaTransito::query()->withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)->whereBetween('created_at', [$inicio, $fin])->count();

        return [
            Stat::make('Estado', $empresa->activa ? 'Activa' : 'Inactiva')
                ->descriptionIcon($empresa->activa ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($empresa->activa ? 'success' : 'danger'),

            Stat::make('Usuarios', $usuarios)
                ->description($modulos.' módulos activos')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Último acceso', $ultimoAcceso
                    ? Carbon::parse($ultimoAcceso)->translatedFormat('d M Y')
                    : 'Sin ingresos')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),

            Stat::make('Consumo del mes', $sms.' SMS · '.$consultas.' consultas')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning'),
        ];
    }
}
