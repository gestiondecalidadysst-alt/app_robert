<?php

namespace App\Filament\App\Resources\Cotizaciones;

use App\Actions\Operacion\ConvertirCotizacion;
use App\Enums\EstadoCotizacion;
use App\Enums\FormaPago;
use App\Enums\ObjetoContrato;
use App\Filament\App\Resources\Cotizaciones\Pages\CrearCotizacion;
use App\Filament\App\Resources\Cotizaciones\Pages\EditarCotizacion;
use App\Filament\App\Resources\Cotizaciones\Pages\ListarCotizaciones;
use App\Filament\App\Resources\Cotizaciones\Schemas\CotizacionForm;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Mail\CorreoCotizacion;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Services\Mensajeria\Mensajero;
use App\Support\Pdf\PdfCotizacion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Cotizaciones de servicios de transporte especial: se arman con líneas Fecha/Ruta/Duración/Valor,
 * se generan en PDF con la marca de la empresa, se envían al cliente y, al aceptarse, se convierten
 * en contrato.
 */
class CotizacionResource extends Resource
{
    protected static ?string $model = Cotizacion::class;

    protected static ?string $slug = 'cotizaciones';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 25;

    protected static ?string $modelLabel = 'cotización';

    protected static ?string $pluralModelLabel = 'cotizaciones';

    protected static ?string $tenantRelationshipName = 'cotizaciones';

    public static function pesos($valor): string
    {
        return '$ '.number_format((float) $valor, 0, ',', '.');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record ? "Cotización {$record->numero_formateado}" : 'Cotización';
    }

    public static function form(Schema $schema): Schema
    {
        return CotizacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['items', 'cliente', 'contrato']))
            ->defaultSort('numero', 'desc')
            ->columns([
                TextColumn::make('numero')->label('No.')->weight('bold')->sortable()->searchable()
                    ->formatStateUsing(fn (Cotizacion $record) => $record->numero_formateado),
                TextColumn::make('destinatario')->label('Cliente')
                    ->state(fn (Cotizacion $record) => $record->destinatarioNombre())
                    ->description(fn (Cotizacion $record) => $record->asunto ? Str::limit($record->asunto, 50) : null)
                    ->searchable(['prospecto_nombre']),
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('valido_hasta')->label('Válida hasta')->date('d/m/Y')->visibleFrom('md'),
                TextColumn::make('total')->label('Total')->alignEnd()
                    ->state(fn (Cotizacion $record) => $record->total())
                    ->formatStateUsing(fn ($state) => self::pesos($state)),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->state(fn (Cotizacion $record) => $record->etiquetaEstado())
                    ->color(fn (Cotizacion $record) => match ($record->etiquetaEstado()) {
                        'Aceptada' => 'success',
                        'Rechazada', 'Anulada' => 'danger',
                        'Enviada' => 'info',
                        'Vencida' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (Cotizacion $record) => $record->convertida() ? 'Contrato '.$record->contrato?->numero_formateado : null),
            ])
            ->filters([
                SelectFilter::make('estado')->options(EstadoCotizacion::class),
            ])
            ->recordActions([
                EditAction::make()->label('Editar')->iconButton()
                    ->visible(fn (Cotizacion $record) => ! $record->anulada() && ! $record->convertida()),
                Action::make('pdf')->label('PDF')->icon('heroicon-o-document-arrow-down')->color('gray')->iconButton()
                    ->action(fn (Cotizacion $record) => response()->streamDownload(
                        fn () => print (PdfCotizacion::generar($record)),
                        PdfCotizacion::nombre($record),
                        ['Content-Type' => 'application/pdf'],
                    )),
                Action::make('enviar')->label('Enviar')->icon('heroicon-o-paper-airplane')->color('primary')->iconButton()
                    ->visible(fn (Cotizacion $record) => ! $record->anulada()
                        && Filament::auth()->user()?->can('enviar_cotizaciones'))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Cotizacion $record) => 'Enviar la cotización '.$record->numero_formateado)
                    ->modalDescription(fn (Cotizacion $record) => $record->destinatarioCorreo()
                        ? 'Se enviará al correo '.$record->destinatarioCorreo().' con el PDF adjunto.'
                        : 'El destinatario no tiene correo. Agrégalo al cliente o al prospecto.')
                    ->action(function (Cotizacion $record, Action $action): void {
                        $correo = $record->destinatarioCorreo();
                        if (blank($correo)) {
                            Notification::make()->danger()->title('Sin correo del destinatario')->send();

                            return;
                        }
                        $empresa = Filament::getTenant();
                        Mensajero::correo($empresa, 'cotizacion', $correo, new CorreoCotizacion(
                            $empresa->nombre_comercial ?: $empresa->razon_social,
                            $record->destinatarioNombre(),
                            $record->numero_formateado,
                            $record->asunto,
                            PdfCotizacion::generar($record),
                            PdfCotizacion::nombre($record),
                        ));
                        if (in_array($record->estado, [EstadoCotizacion::Borrador], true)) {
                            $record->forceFill(['estado' => EstadoCotizacion::Enviada, 'enviada_at' => now()])->save();
                        } else {
                            $record->forceFill(['enviada_at' => now()])->save();
                        }
                        Notification::make()->success()->title('Cotización enviada')->body('Se encoló el correo para '.$correo.'.')->send();
                    }),
                Action::make('aceptada')->label('Aceptada')->icon('heroicon-o-check')->color('success')->iconButton()
                    ->visible(fn (Cotizacion $record) => ! $record->anulada() && ! $record->convertida()
                        && $record->estado !== EstadoCotizacion::Aceptada)
                    ->action(fn (Cotizacion $record) => $record->forceFill(['estado' => EstadoCotizacion::Aceptada])->save())
                    ->successNotificationTitle('Marcada como aceptada'),
                Action::make('rechazada')->label('Rechazada')->icon('heroicon-o-x-mark')->color('danger')->iconButton()
                    ->visible(fn (Cotizacion $record) => ! $record->anulada() && ! $record->convertida()
                        && $record->estado !== EstadoCotizacion::Rechazada)
                    ->requiresConfirmation()
                    ->action(fn (Cotizacion $record) => $record->forceFill(['estado' => EstadoCotizacion::Rechazada])->save())
                    ->successNotificationTitle('Marcada como rechazada'),
                Action::make('convertir')->label('Convertir en contrato')->icon('heroicon-o-document-plus')->color('primary')->iconButton()
                    ->visible(fn (Cotizacion $record) => ! $record->anulada() && ! $record->convertida())
                    ->modalHeading(fn (Cotizacion $record) => 'Convertir '.$record->numero_formateado.' en contrato')
                    ->modalDescription('El contrato toma el cliente, el asunto y el valor total de la cotización. Completa los datos propios del contrato.')
                    ->schema([
                        Select::make('cliente_id')->label('Cliente')->required()
                            ->options(fn () => Cliente::query()->orderBy('nombre')->pluck('nombre', 'id'))
                            ->default(fn (Cotizacion $record) => $record->cliente_id)
                            ->searchable()
                            ->helperText(fn (Cotizacion $record) => $record->cliente_id ? null : 'Era un prospecto: elige o registra el cliente.'),
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            DatePicker::make('fecha_inicio')->label('Inicio de vigencia')->required()->default(today()),
                            DatePicker::make('fecha_fin')->label('Fin de vigencia')->required()->afterOrEqual('fecha_inicio'),
                            Select::make('objeto')->label('Objeto')->options(ObjetoContrato::class)->required(),
                            Select::make('forma_pago')->label('Forma de pago')->options(FormaPago::class),
                        ]),
                    ])
                    ->action(function (Cotizacion $record, array $data, Action $action): void {
                        $contrato = ErroresDeNegocio::intentar($action, fn () => app(ConvertirCotizacion::class)($record, $data));
                        Notification::make()->success()
                            ->title('Contrato '.$contrato->numero_formateado.' creado')
                            ->body('Desde la cotización '.$record->numero_formateado.'. Ya puedes expedir su FUEC.')
                            ->send();
                    }),
                Action::make('anular')->label('Anular')->icon('heroicon-o-trash')->color('danger')->iconButton()
                    ->visible(fn (Cotizacion $record) => ! $record->anulada() && ! $record->convertida()
                        && Filament::auth()->user()?->can('anular_cotizaciones'))
                    ->requiresConfirmation()
                    ->action(fn (Cotizacion $record) => $record->forceFill(['anulado_at' => now()])->save())
                    ->successNotificationTitle('Cotización anulada'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarCotizaciones::route('/'),
            'create' => CrearCotizacion::route('/crear'),
            'edit' => EditarCotizacion::route('/{record}/editar'),
        ];
    }
}
