<?php

namespace App\Providers;

use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Cola: latido del worker (Estado del sistema) y referencia de error nueva por cada trabajo.
        \Illuminate\Support\Facades\Queue::looping(fn () => \App\Support\Sistema\Latidos::marcar(\App\Support\Sistema\Latidos::WORKER));
        \Illuminate\Support\Facades\Queue::before(function (\Illuminate\Queue\Events\JobProcessing $evento): void {
            // Los trabajos síncronos (sync) son parte de la misma petición: conservan su referencia.
            if ($evento->connectionName !== 'sync') {
                \App\Support\Sistema\Referencia::reiniciar();
            }
        });

        // Páginas públicas de firma: límites por IP, cada uno con su propia clave.
        foreach (['firma' => 60, 'firma-codigo' => 8, 'firma-firmar' => 20, 'verificar-archivo' => 10, 'conductor-ingreso' => 10] as $nombre => $porMinuto) {
            RateLimiter::for($nombre, fn (Request $request) => Limit::perMinute($porMinuto)->by($nombre . '|' . $request->ip()));
        }

        // Fechas siempre como dd/mm/aaaa, sin depender del idioma del navegador.
        DatePicker::configureUsing(fn (DatePicker $campo) => $campo->native(false)->displayFormat('d/m/Y')->firstDayOfWeek(1));

        Carbon::setLocale(config('app.locale'));

        // Detecta errores comunes durante el desarrollo (carga perezosa, atributos inexistentes).
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // El superadministrador de la plataforma tiene todos los permisos.
        Gate::before(fn (User $user) => $user->es_superadmin ? true : null);

        // Pie del menú lateral: nombre de la plataforma.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_FOOTER,
            fn (): string => Blade::render('<div class="ruta-sidebar-footer">Plataforma de transporte · SolucionesApp</div>'),
        );
    }
}
