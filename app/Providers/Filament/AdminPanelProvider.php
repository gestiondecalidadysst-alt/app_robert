<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\EstiloRuta;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use App\Filament\Admin\Pages\Inicio;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel del superadministrador de SolucionesApp: empresas, catálogos nacionales y usuarios.
 */
class AdminPanelProvider extends PanelProvider
{
    use EstiloRuta;

    public function panel(Panel $panel): Panel
    {
        return $this->aplicarEstiloRuta($panel)
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Ruta · Plataforma')
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Inicio::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Plataforma'),
                NavigationGroup::make('Ubicación'),
                NavigationGroup::make('Vehículos'),
                NavigationGroup::make('Personas'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
