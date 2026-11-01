<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\Inicio;
use App\Filament\App\Pages\Tenancy\EditarEmpresa;
use App\Http\Middleware\PrepararEmpresaActiva;
use App\Models\Empresa;
use App\Providers\Filament\Concerns\EstiloRuta;
use Filament\View\PanelsRenderHook;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel de cada empresa de transporte (multiempresa): /app/{empresa}/...
 */
class AppPanelProvider extends PanelProvider
{
    use EstiloRuta;

    public function panel(Panel $panel): Panel
    {
        return $this->aplicarEstiloRuta($panel)
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->tenant(Empresa::class, slugAttribute: 'slug', ownershipRelationship: 'empresa')
            ->tenantProfile(EditarEmpresa::class)
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->searchableTenantMenu()
            ->tenantMiddleware([
                PrepararEmpresaActiva::class,
            ], isPersistent: true)
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn () => view('filament.partes.empresa-topbar'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.partes.rol-topbar'))
            // Los conductores entran por /conductor con su documento, no por aquí.
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.partes.enlace-portal-conductor'))
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\\Filament\\App\\Pages')
            ->pages([
                Inicio::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\\Filament\\App\\Widgets')
            ->navigationGroups([
                NavigationGroup::make('Flota'),
                NavigationGroup::make('Conductores'),
                NavigationGroup::make('Operación'),
                NavigationGroup::make('Seguridad vial'),
                NavigationGroup::make('Mantenimiento'),
                NavigationGroup::make('Finanzas'),
                NavigationGroup::make('Gerencia'),
                NavigationGroup::make('Configuración'),
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
