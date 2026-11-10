<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\EstadoSistema;
use App\Models\EnvioMensaje;
use App\Models\ErrorSistema;
use App\Models\WebhookEntrega;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Salud de la plataforma de un vistazo: trabajos y envíos fallidos, webhooks pendientes y
 * errores abiertos. Cada tarjeta lleva a la pantalla de Estado del sistema para actuar.
 */
class EstadoPlataforma extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected ?string $heading = 'Estado del sistema';

    protected function getStats(): array
    {
        $fallidos = DB::table('failed_jobs')->count();
        $enviosFallidos = EnvioMensaje::query()->withoutGlobalScopes()
            ->where('estado', 'fallido')->where('created_at', '>=', now()->subDays(7))->count();
        $webhooksPendientes = WebhookEntrega::query()->withoutGlobalScopes()->where('estado', 'pendiente')->count();
        $erroresAbiertos = ErrorSistema::query()->abiertos()->count();

        $url = EstadoSistema::getUrl();

        return [
            Stat::make('Trabajos fallidos', $fallidos)
                ->description('En la cola de procesos')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($fallidos > 0 ? 'danger' : 'success')
                ->url($url),

            Stat::make('SMS y correos fallidos', $enviosFallidos)
                ->description('Últimos 7 días')
                ->descriptionIcon('heroicon-m-envelope')
                ->color($enviosFallidos > 0 ? 'warning' : 'success')
                ->url($url),

            Stat::make('Webhooks pendientes', $webhooksPendientes)
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($webhooksPendientes > 0 ? 'warning' : 'success')
                ->url($url),

            Stat::make('Errores abiertos', $erroresAbiertos)
                ->descriptionIcon('heroicon-m-bug-ant')
                ->color($erroresAbiertos > 0 ? 'danger' : 'success')
                ->url($url),
        ];
    }
}
