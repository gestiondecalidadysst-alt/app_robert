<?php

namespace App\Providers\Filament\Concerns;

use App\Filament\Soporte\AvatarIniciales;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;

/**
 * Apariencia común de los paneles (estilo acordado en docs/DECISIONES.md).
 */
trait EstiloRuta
{
    protected function aplicarEstiloRuta(Panel $panel): Panel
    {
        return $panel
            ->brandName('Ruta')
            ->brandLogo(fn () => view('filament.partes.logo'))
            ->brandLogoHeight('2rem')
            ->colors([
                'primary' => static::paletaNaranja(),
                'gray' => Color::Slate,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Sky,
            ])
            ->font('Inter')
            ->defaultAvatarProvider(AvatarIniciales::class)
            ->assets([
                Css::make('ruta', resource_path('css/ruta.css')),
            ])
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->databaseNotifications(false);
    }

    /**
     * Naranja de Tailwind oscurecido: botones (tonos 500 y 600) en #C2410C,
     * que da contraste suficiente con texto blanco.
     */
    protected static function paletaNaranja(): array
    {
        $o = Color::Orange;

        // array_replace (y no "...") para conservar las llaves numéricas 50, 100... 950.
        return array_replace($o, [
            400 => $o[600],
            500 => $o[700],
            600 => $o[700],
            700 => $o[800],
            800 => $o[900],
            900 => $o[950],
        ]);
    }
}
