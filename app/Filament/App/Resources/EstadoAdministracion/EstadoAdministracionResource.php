<?php

namespace App\Filament\App\Resources\EstadoAdministracion;

use App\Actions\Administracion\RegistrarPagoAdministracion;
use App\Enums\MetodoPago;
use App\Filament\App\Resources\EstadoAdministracion\Pages\ListEstadoAdministracion;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\CuotaAdministracion;
use App\Models\PagoAdministracion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Estado de cuenta de administración: la cuota mensual de cada vehículo, mes a mes, con lo pagado
 * y lo pendiente. Desde aquí se registra el pago (que se reparte de la cuota más vieja a la más nueva).
 */
class EstadoAdministracionResource extends Resource
{
    protected static ?string $model = CuotaAdministracion::class;

    protected static ?string $slug = 'administracion/estado-cuenta';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'cuota de administración';

    protected static ?string $pluralModelLabel = 'estado de cuenta';

    protected static ?string $navigationLabel = 'Estado de cuenta';

    protected static ?string $breadcrumb = 'Estado de cuenta';

    public static function pesos($valor): string
    {
        return '$ '.number_format((float) $valor, 0, ',', '.');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehiculo.propietario', 'propietario']))
            ->defaultSort('periodo', 'desc')
            ->defaultGroup(Group::make('vehiculo.placa')->label('Vehículo')->collapsible())
            ->recordClasses(fn (CuotaAdministracion $record) => $record->anulada() ? 'opacity-60' : null)
            ->columns([
                TextColumn::make('vehiculo.placa')->label('Vehículo')->weight('bold')->searchable()->sortable()
                    ->description(fn (CuotaAdministracion $record) => $record->propietario?->nombre_completo ?? $record->vehiculo?->propietario?->nombre_completo),
                TextColumn::make('periodo')->label('Mes')->sortable()
                    ->formatStateUsing(fn (CuotaAdministracion $record) => $record->periodoTexto()),
                TextColumn::make('valor')->label('Cuota')->formatStateUsing(fn ($state) => self::pesos($state))->alignEnd(),
                TextColumn::make('pagado')->label('Pagado')->formatStateUsing(fn ($state) => self::pesos($state))->alignEnd()->visibleFrom('md'),
                TextColumn::make('saldo')->label('Pendiente')->alignEnd()
                    ->state(fn (CuotaAdministracion $record) => $record->saldo())
                    ->formatStateUsing(fn ($state) => self::pesos($state))->weight('bold'),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->state(fn (CuotaAdministracion $record) => $record->etiquetaEstado())
                    ->color(fn (CuotaAdministracion $record) => match ($record->estado()) {
                        'pagada' => 'success',
                        'parcial' => 'warning',
                        'anulada' => 'gray',
                        default => $record->enMora() ? 'danger' : 'info',
                    })
                    ->description(fn (CuotaAdministracion $record) => $record->enMora() ? 'En mora' : null),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(['pendiente' => 'Pendiente', 'parcial' => 'Abonada', 'pagada' => 'Pagada', 'anulada' => 'Anulada'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'pendiente' => $query->whereNull('anulado_at')->where('pagado', 0)->whereColumn('pagado', '<', 'valor'),
                        'parcial' => $query->whereNull('anulado_at')->where('pagado', '>', 0)->whereColumn('pagado', '<', 'valor'),
                        'pagada' => $query->whereNull('anulado_at')->whereColumn('pagado', '>=', 'valor'),
                        'anulada' => $query->whereNotNull('anulado_at'),
                        default => $query,
                    }),
                Filter::make('en_mora')
                    ->label('Solo en mora')
                    ->query(fn (Builder $query) => $query->whereNull('anulado_at')
                        ->whereColumn('pagado', '<', 'valor')
                        ->whereDate('periodo', '<', now()->startOfMonth()->toDateString())),
            ])
            ->recordActions([
                Action::make('pagar')->iconButton()
                    ->label('Registrar pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(fn (CuotaAdministracion $record) => ! $record->anulada()
                        && (int) ($record->vehiculo?->saldoAdministracion() ?? 0) > 0
                        && Filament::auth()->user()?->can('create', PagoAdministracion::class))
                    ->modalHeading(fn (CuotaAdministracion $record) => 'Registrar pago · '.$record->vehiculo?->placa
                        .' · pendiente '.self::pesos($record->vehiculo?->saldoAdministracion() ?? 0))
                    ->modalDescription('El pago se aplica a los meses pendientes, del más viejo al más nuevo, y genera el recibo.')
                    ->modalSubmitActionLabel('Registrar y generar recibo')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            DatePicker::make('fecha')->label('Fecha')->default(now())->maxDate(now())->required(),
                            TextInput::make('valor')->label('Valor')->numeric()->prefix('$')->minValue(1)->required()
                                ->default(fn (CuotaAdministracion $record) => $record->vehiculo?->saldoAdministracion())
                                ->helperText(fn (CuotaAdministracion $record) => 'Máximo '.self::pesos($record->vehiculo?->saldoAdministracion() ?? 0).' (lo pendiente del vehículo).'),
                            Select::make('metodo')->label('Método')->options(MetodoPago::class)->default(MetodoPago::Efectivo->value)->required(),
                            TextInput::make('referencia')->label('Referencia')->maxLength(255)->placeholder('No. de transferencia, voucher…'),
                        ]),
                        Textarea::make('observacion')->label('Observación')->rows(2),
                    ])
                    ->action(function (CuotaAdministracion $record, array $data, Action $action): void {
                        $pago = ErroresDeNegocio::intentar($action, fn () => app(RegistrarPagoAdministracion::class)(
                            $record->vehiculo,
                            [...$data, 'user_id' => Filament::auth()->id()],
                        ));

                        Notification::make()->success()
                            ->title("Pago registrado · recibo {$pago->numero_recibo}")
                            ->body('Abono de '.self::pesos($pago->valor).'. Saldo del vehículo: '.self::pesos($record->vehiculo?->saldoAdministracion() ?? 0).'.')
                            ->persistent()
                            ->actions([
                                Action::make('recibo')->label('Ver recibo')->button()
                                    ->url(fn () => route('administracion.recibo', $pago), shouldOpenInNewTab: true),
                            ])
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEstadoAdministracion::route('/'),
        ];
    }
}
