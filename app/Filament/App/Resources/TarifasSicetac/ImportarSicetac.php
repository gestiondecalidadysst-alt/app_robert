<?php

namespace App\Filament\App\Resources\TarifasSicetac;

use App\Support\Carga\Sicetac;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Carga masiva de costos mínimos pegando el CSV que publica el Ministerio.
 *
 * Se pega el texto en vez de subir un archivo: las tablas se copian de una hoja de
 * cálculo y así la empresa no tiene que exportarlas antes.
 */
final class ImportarSicetac
{
    public static function accion(): Action
    {
        return Action::make('importar')->label('Cargar tabla')->icon('heroicon-o-arrow-up-tray')->color('gray')
            // Sin esto cualquiera que vea la pantalla podría poner el mínimo en $1 y
            // dejar pasar fletes por debajo del tope legal, o bloquear la expedición.
            ->visible(fn () => auth()->user()?->can('crear_tarifas_sicetac') ?? false)
            ->modalHeading('Cargar costos mínimos del SICE-TAC')
            ->modalSubmitActionLabel('Cargar')
            ->schema([
                Placeholder::make('formato')->hiddenLabel()->content(new HtmlString(
                    'Una línea por ruta, separada por punto y coma:<br>'
                    .'<code>origen;destino;configuracion;valor;desde;hasta</code><br>'
                    .'Los municipios van por <b>código DANE</b> (5 dígitos). La configuración puede ir vacía '
                    .'(aplica a cualquiera) y las fechas también (desde hoy, sin caducidad).<br>'
                    .'Ejemplo: <code>23001;11001;50;2850000;2026-01-01;</code>'
                )),
                Textarea::make('csv')->hiddenLabel()->required()->rows(10)
                    ->maxLength(200000)
                    ->placeholder("23001;11001;50;2850000;2026-01-01;\n76001;11001;;3100000;;"),
            ])
            ->action(function (array $data): void {
                $empresa = Filament::getTenant();
                [$cargadas, $errores] = Sicetac::importar((int) $empresa->getKey(), $data['csv']);

                if ($cargadas === 0) {
                    Notification::make()->danger()->title('No se cargó ninguna tarifa')
                        ->body(implode(' ', array_slice($errores, 0, 3)) ?: 'Revise el formato.')->persistent()->send();

                    return;
                }

                Notification::make()->success()->title($cargadas.' tarifa(s) cargadas')
                    ->body($errores === [] ? 'Todas las líneas se leyeron bien.'
                        : count($errores).' línea(s) con problemas: '.implode(' ', array_slice($errores, 0, 3)))
                    ->persistent($errores !== [])->send();
            });
    }
}
