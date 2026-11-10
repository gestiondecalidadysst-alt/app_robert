<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Empresas\EmpresaResource;
use App\Filament\Admin\Resources\Usuarios\UsuarioPlataformaResource;
use App\Models\ConsultaTransito;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\EnvioMensaje;
use App\Models\Fuec;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Pulso de la plataforma para el superadministrador: empresas, usuarios, actividad del mes
 * (FUEC y contratos) y consumo facturable del mes (SMS y consultas de tránsito).
 */
class ResumenPlataforma extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|array|null $columns = ['md' => 2, 'xl' => 3];

    protected function getStats(): array
    {
        $inicio = Carbon::now()->startOfMonth();
        $fin = Carbon::now()->endOfMonth();

        $empresas = Empresa::query()->withoutGlobalScopes()->count();
        $activas = Empresa::query()->withoutGlobalScopes()->where('activa', true)->count();

        $usuarios = User::query()->withoutGlobalScopes()->count();
        $usuariosActivos = User::query()->withoutGlobalScopes()->where('activo', true)->count();

        $fuecMes = Fuec::query()->withoutGlobalScopes()->whereBetween('created_at', [$inicio, $fin])->count();
        $contratosMes = Contrato::query()->withoutGlobalScopes()->whereBetween('created_at', [$inicio, $fin])->count();

        $sms = EnvioMensaje::query()->withoutGlobalScopes()
            ->where('canal', 'sms')->where('estado', 'enviado')
            ->whereBetween('created_at', [$inicio, $fin])
            ->selectRaw('count(*) as mensajes, coalesce(sum(segmentos),0) as segmentos, coalesce(sum(precio),0) as cobrar')
            ->first();

        $consultas = ConsultaTransito::query()->withoutGlobalScopes()
            ->whereBetween('created_at', [$inicio, $fin])
            ->selectRaw('count(*) as total, coalesce(sum(case when cobrada = 1 then precio else 0 end), 0) as cobrar')
            ->first();

        return [
            Stat::make('Empresas', $empresas)
                ->description("{$activas} activas · ".($empresas - $activas).' inactivas')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color($activas > 0 ? 'success' : 'gray')
                ->url(EmpresaResource::getUrl()),

            Stat::make('Usuarios de la plataforma', $usuarios)
                ->description("{$usuariosActivos} activos")
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->url(UsuarioPlataformaResource::getUrl()),

            Stat::make('FUEC expedidos (mes)', $fuecMes)
                ->description("{$contratosMes} contratos creados este mes")
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('SMS enviados (mes)', (int) ($sms->mensajes ?? 0))
                ->description(((int) ($sms->segmentos ?? 0)).' segmentos · '.$this->pesos($sms->cobrar ?? 0).' a cobrar')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('warning'),

            Stat::make('Consultas de tránsito (mes)', (int) ($consultas->total ?? 0))
                ->description($this->pesos($consultas->cobrar ?? 0).' a cobrar')
                ->descriptionIcon('heroicon-m-magnifying-glass')
                ->color('warning'),
        ];
    }

    private function pesos(int|float|string $valor): string
    {
        return '$'.number_format((float) $valor, 0, ',', '.');
    }
}
