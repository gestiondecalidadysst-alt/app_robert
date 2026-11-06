<?php

namespace App\Filament\App\Resources\RecibosAdministracion;

use App\Actions\Administracion\AnularPagoAdministracion;
use App\Filament\App\Resources\RecibosAdministracion\Pages\ListRecibosAdministracion;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\PagoAdministracion;
use App\Support\Pdf\PdfReciboAdministracion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Recibos de los pagos de administración: descargar el PDF y anular (devolviendo el abono a las cuotas).
 */
class ReciboAdministracionResource extends Resource
{
    protected static ?string $model = PagoAdministracion::class;

    protected static ?string $slug = 'administracion/recibos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'recibo de administración';

    protected static ?string $pluralModelLabel = 'recibos de administración';

    protected static ?string $navigationLabel = 'Recibos';

    protected static ?string $breadcrumb = 'Recibos';

    public static function pesos($valor): string
    {
        return '$ '.number_format((float) $valor, 0, ',', '.');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehiculo.propietario', 'usuario']))
            ->defaultSort(fn ($query) => $query->orderByDesc('fecha')->orderByDesc('id'))
            ->emptyStateHeading('Sin recibos')
            ->emptyStateDescription('Cada pago de administración genera su recibo.')
            ->recordClasses(fn (PagoAdministracion $record) => $record->anulado() ? 'opacity-60' : null)
            ->columns([
                TextColumn::make('numero')->label('Recibo')->weight('bold')
                    ->formatStateUsing(fn (PagoAdministracion $record) => $record->numero_recibo)
                    ->description(fn (PagoAdministracion $record) => $record->anulado() ? 'Anulado' : null)
                    ->searchable(),
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('vehiculo.placa')->label('Vehículo')->searchable()
                    ->description(fn (PagoAdministracion $record) => $record->vehiculo?->propietario?->nombre_completo),
                TextColumn::make('valor')->label('Valor')->formatStateUsing(fn ($state) => self::pesos($state))->alignEnd(),
                TextColumn::make('metodo')->label('Método')->badge()->color('gray')->visibleFrom('md'),
                TextColumn::make('referencia')->label('Referencia')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('usuario.name')->label('Registró')->placeholder('—')->visibleFrom('lg'),
            ])
            ->filters([
                Filter::make('vigentes')
                    ->label('Ocultar anulados')
                    ->query(fn (Builder $query) => $query->whereNull('anulado_at'))
                    ->default(),
            ])
            ->recordActions([
                Action::make('recibo')
                    ->label('Recibo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (PagoAdministracion $record) => response()->streamDownload(
                        fn () => print (PdfReciboAdministracion::recibo($record)),
                        PdfReciboAdministracion::nombreRecibo($record),
                        ['Content-Type' => 'application/pdf'],
                    )),
                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PagoAdministracion $record) => ! $record->anulado())
                    ->authorize(fn (PagoAdministracion $record) => Filament::auth()->user()?->can('anular', $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (PagoAdministracion $record) => "Anular el recibo {$record->numero_recibo}")
                    ->modalDescription('El pago se devuelve a las cuotas abonadas (vuelven a quedar pendientes). El recibo conserva su número y queda marcado como ANULADO.')
                    ->schema([Textarea::make('motivo')->label('Motivo')->required()->rows(2)])
                    ->action(function (PagoAdministracion $record, array $data, Action $action): void {
                        ErroresDeNegocio::intentar($action, fn () => app(AnularPagoAdministracion::class)($record, $data['motivo'], Filament::auth()->id()));
                    })
                    ->successNotificationTitle('Recibo anulado'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecibosAdministracion::route('/'),
        ];
    }
}
