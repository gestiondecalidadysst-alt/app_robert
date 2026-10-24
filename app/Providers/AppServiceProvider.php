<?php

namespace App\Providers;

use App\Models\User;
use Carbon\Carbon;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
