<?php

namespace App\Filament\App\Carga;

use App\Models\Empresa;
use App\Services\Rndc\Rndc;
use App\Support\Pdf\PdfFichaRndc;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Acciones del RNDC para terceros, propietarios, conductores, vehículos y remolques:
 * registrar por web service (si la empresa está conectada), anotar el radicado de un registro hecho
 * en la web del RNDC, descargar la ficha en PDF y ver el XML.
 */
final class AccionesRndc
{
    public static function puedeVer(): bool
    {
        return auth()->user()?->can('ver_rndc') ?? false;
    }

    public static function puedeRegistrar(): bool
    {
        return auth()->user()?->can('crear_rndc') ?? false;
    }

    private static function conectado(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant && Rndc::conectado(Empresa::find($tenant->getKey()));
    }

    public static function grupo(): ActionGroup
    {
        return ActionGroup::make([
            self::registrar(),
            self::manual(),
            self::ficha(),
            self::xml(),
        ])->label('RNDC')->icon('heroicon-o-cloud-arrow-up')->button()->color('gray')->visible(fn () => self::puedeVer());
    }

    public static function registrar(): Action
    {
        return Action::make('rndc_registrar')
            ->label('Registrar en el RNDC')
            ->icon('heroicon-o-cloud-arrow-up')
            ->visible(fn () => self::puedeRegistrar() && self::conectado())
            ->requiresConfirmation()
            ->modalHeading('Registrar en el RNDC')
            ->modalDescription(fn (Model $record) => ($p = Rndc::pendientes($record))
                ? 'Corrija primero: ' . implode(' ', $p)
                : 'Se envían los datos de ' . $record->etiquetaRndc() . ' al Registro Nacional de Despachos de Carga.')
            ->modalSubmitAction(fn (Action $action, Model $record) => $action->getModalSubmitAction()->disabled(Rndc::pendientes($record) !== []))
            ->action(function (Model $record): void {
                try {
                    $registro = app(Rndc::class)->registrar($record, auth()->user());
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('No se envió al RNDC')->body(implode(' ', collect($e->errors())->flatten()->all()))->persistent()->send();

                    return;
                }
                $registro->estado === 'registrado'
                    ? Notification::make()->success()->title('Registrado en el RNDC')->body("Radicado No. {$registro->ingresoid}.")->send()
                    : Notification::make()->danger()->title('El RNDC rechazó el registro')
                        ->body(collect($registro->errores)->map(fn ($e) => trim(($e['codigo'] ? $e['codigo'] . ': ' : '') . $e['mensaje']))->implode("\n"))->persistent()->send();
            });
    }

    public static function manual(): Action
    {
        return Action::make('rndc_manual')
            ->label('Anotar radicado de la web del RNDC')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn () => self::puedeRegistrar())
            ->modalHeading('Registro hecho en la web del RNDC')
            ->modalDescription('Si registró estos datos directamente en la web del RNDC, anote el número de radicado que le entregó.')
            ->schema([TextInput::make('ingresoid')->label('Número de radicado del RNDC')->required()->numeric()->maxLength(30)])
            ->action(function (Model $record, array $data): void {
                app(Rndc::class)->marcarManual($record, (string) $data['ingresoid'], auth()->user());
                Notification::make()->success()->title('Radicado anotado')->send();
            });
    }

    public static function ficha(): Action
    {
        return Action::make('rndc_ficha')
            ->label('Ficha RNDC (PDF)')
            ->icon('heroicon-o-document-arrow-down')
            ->visible(fn () => self::puedeVer())
            ->action(fn (Model $record) => response()->streamDownload(fn () => print(PdfFichaRndc::fichas([$record])), PdfFichaRndc::nombre($record), ['Content-Type' => 'application/pdf']));
    }

    public static function xml(): Action
    {
        return Action::make('rndc_xml')
            ->label('Ver XML')
            ->icon('heroicon-o-code-bracket')
            ->visible(fn () => self::puedeRegistrar())
            ->modalHeading('XML para el web service del RNDC')
            ->modalDescription('Así se enviará (la clave no se muestra). Sirve para revisar los datos o probar en la herramienta de pruebas del RNDC.')
            ->modalContent(fn (Model $record) => view('filament.app.carga.xml-rndc', ['xml' => Rndc::xml($record), 'pendientes' => Rndc::pendientes($record)]))
            ->modalSubmitAction(false)->modalCancelActionLabel('Cerrar');
    }

    public static function fichasMasivas(): BulkAction
    {
        return BulkAction::make('rndc_fichas')
            ->label('Fichas RNDC (PDF)')
            ->icon('heroicon-o-document-arrow-down')
            ->visible(fn () => self::puedeVer())
            ->action(fn (Collection $records) => response()->streamDownload(fn () => print(PdfFichaRndc::fichas($records)), 'fichas-rndc-' . now()->format('Ymd-His') . '.pdf', ['Content-Type' => 'application/pdf']));
    }

    public static function columnaEstado(): TextColumn
    {
        return TextColumn::make('estado_rndc')
            ->label('RNDC')
            ->badge()
            ->state(fn (Model $record) => $record->estadoRndc())
            ->formatStateUsing(fn (string $state, Model $record) => $record::etiquetaEstadoRndc($state))
            ->color(fn (string $state, Model $record) => $record::colorEstadoRndc($state))
            ->description(fn (Model $record) => $record->ultimoRegistroRndc?->ingresoid ? 'No. ' . $record->ultimoRegistroRndc->ingresoid : null)
            ->visible(fn () => self::puedeVer());
    }
}
