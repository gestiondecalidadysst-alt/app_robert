<?php

namespace App\Filament\App\Carga;

use App\Models\RemesaCarga;
use App\Models\TiempoLogistico;
use App\Support\Carga\TiemposLogisticos;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;

/**
 * Tiempos logísticos desde la oficina.
 *
 * Lo normal es que los marque el conductor desde el celular, en el momento. Esto es
 * para corregirlos o completarlos cuando no pudo (sin señal, celular descargado):
 * un tiempo tardío sigue siendo mejor que ninguno ante el Ministerio.
 */
final class TiemposDeRemesa
{
    public static function accion(): Action
    {
        return Action::make('tiempos')->label('Tiempos logísticos')->icon('heroicon-o-clock')
            ->color(fn (RemesaCarga $r) => TiemposLogisticos::completos($r) ? 'gray' : 'warning')
            ->modalHeading('Tiempos logísticos de la remesa')
            ->modalDescription('Llegada, entrada y salida en cada punto. El Ministerio los exige en tiempo real; lo ideal es que los marque el conductor desde el portal.')
            ->modalSubmitActionLabel('Guardar')
            ->visible(fn (RemesaCarga $r) => auth()->user()?->can('crear_cumplidos_carga') && ! $r->anulado())
            ->fillForm(function (RemesaCarga $r): array {
                $datos = [];
                foreach (TiemposLogisticos::registrados($r) as $clave => $tiempo) {
                    $datos[str_replace('.', '_', $clave)] = $tiempo->ocurrido_at;
                }

                return $datos;
            })
            ->schema(fn (RemesaCarga $r) => collect(TiempoLogistico::PUNTOS)
                ->map(fn (string $etiqueta, string $punto) => Section::make($etiqueta)
                    ->description(self::resumenEspera($r, $punto))
                    ->columns(3)
                    ->schema(collect(TiempoLogistico::EVENTOS)
                        ->map(fn (string $nombre, string $evento) => DateTimePicker::make("{$punto}_{$evento}")
                            ->label($nombre)->seconds(false)->native(false)
                            ->maxDate(now()->addDay()))
                        ->values()->all()))
                ->values()->all())
            ->action(function (RemesaCarga $r, array $data): void {
                $guardados = 0;

                // En orden, para que las validaciones de secuencia se cumplan igual que en el celular.
                foreach (array_keys(TiempoLogistico::PUNTOS) as $punto) {
                    foreach (TiempoLogistico::ORDEN as $evento) {
                        $valor = $data["{$punto}_{$evento}"] ?? null;
                        $existente = $r->tiemposLogisticos()->where('punto', $punto)->where('evento', $evento)->first();

                        if (blank($valor)) {
                            continue;
                        }

                        if ($existente) {
                            $existente->update(['ocurrido_at' => $valor]);
                            $guardados++;

                            continue;
                        }

                        TiemposLogisticos::marcar($r, $punto, $evento, null, auth()->user(), ['ocurrido_at' => Carbon::parse($valor)]);
                        $guardados++;
                    }
                }

                TiemposLogisticos::volcarEnLaRemesa($r->fresh());

                $excedente = TiemposLogisticos::excedente($r->fresh(), 'cargue') + TiemposLogisticos::excedente($r->fresh(), 'descargue');

                Notification::make()->success()->title($guardados.' tiempo(s) guardados')
                    ->body($excedente > 0
                        ? 'Se excedió lo pactado en '.number_format($excedente, 1, ',', '.').' h: ese tiempo de espera es cobrable.'
                        : 'Dentro de los tiempos pactados.')
                    ->send();
            });
    }

    /** Columna compacta: cuántos de los seis momentos están reportados. */
    public static function columna(): TextColumn
    {
        return TextColumn::make('tiempos')->label('Tiempos')->badge()
            ->state(fn (RemesaCarga $r) => $r->tiemposLogisticos->count().'/6')
            ->color(fn (RemesaCarga $r) => match (true) {
                $r->tiemposLogisticos->count() === 6 => 'success',
                $r->tiemposLogisticos->count() === 0 => 'gray',
                default => 'warning',
            })
            ->tooltip(fn (RemesaCarga $r) => $r->tiemposLogisticos
                ->map(fn (TiempoLogistico $t) => $t->etiqueta().': '.$t->ocurrido_at->format('d/m h:i a'))
                ->implode(' · ') ?: 'Sin tiempos reportados');
    }

    private static function resumenEspera(RemesaCarga $remesa, string $punto): string
    {
        $real = TiemposLogisticos::espera($remesa, $punto);
        $pactadas = TiemposLogisticos::pactadas($remesa, $punto);

        if ($real === null) {
            return 'Pactado: '.number_format($pactadas, 1, ',', '.').' h.';
        }

        $excedente = max(0, $real - $pactadas);

        return 'Espera real: '.number_format($real, 1, ',', '.').' h de '.number_format($pactadas, 1, ',', '.').' h pactadas.'
            .($excedente > 0 ? ' Excedente cobrable: '.number_format($excedente, 1, ',', '.').' h.' : '');
    }

    /** Aviso para el formulario cuando aún no hay nada reportado. */
    public static function aviso(): Placeholder
    {
        return Placeholder::make('aviso_tiempos')->hiddenLabel()
            ->content('Los tiempos los marca el conductor desde el portal, en el momento.');
    }
}
