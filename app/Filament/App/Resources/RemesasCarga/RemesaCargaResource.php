<?php

namespace App\Filament\App\Resources\RemesasCarga;

use App\Filament\App\Carga\AccionesRndc;
use App\Filament\App\Carga\AnularConRndc;
use App\Filament\App\Carga\TiemposDeRemesa;
use App\Filament\App\Resources\RemesasCarga\Pages\CrearRemesa;
use App\Filament\App\Resources\RemesasCarga\Pages\EditarRemesa;
use App\Filament\App\Resources\RemesasCarga\Pages\ListarRemesas;
use App\Filament\App\Resources\RemesasCarga\Schemas\RemesaCargaForm;
use App\Models\CumplidoRemesa;
use App\Models\RemesaCarga;
use App\Support\Carga\TiemposLogisticos;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Remesas terrestres de carga: documento oficial por envío (proceso 3 del RNDC). */
class RemesaCargaResource extends Resource
{
    protected static ?string $model = RemesaCarga::class;

    protected static ?string $slug = 'remesas-carga';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Carga';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Remesas';

    protected static ?string $modelLabel = 'remesa';

    protected static ?string $pluralModelLabel = 'remesas terrestres';

    protected static ?string $tenantRelationshipName = 'remesasCarga';

    public static function form(Schema $schema): Schema
    {
        return RemesaCargaForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['remitente', 'destinatario', 'manifiesto', 'ultimoRegistroRndc', 'tiemposLogisticos']))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Expida su primera remesa')
            ->columns([
                TextColumn::make('numero')->label('No.')->formatStateUsing(fn (RemesaCarga $r) => $r->numeroFormateado())->weight('bold'),
                TextColumn::make('fecha')->date('d/m/Y'),
                TextColumn::make('producto_descripcion')->label('Producto')->wrap()->limit(40)->description(fn (RemesaCarga $r) => RemesaCarga::OPERACIONES[$r->operacion] ?? ''),
                TextColumn::make('cantidad')->numeric(thousandsSeparator: '.')->suffix(fn (RemesaCarga $r) => $r->unidad === 2 ? ' gal' : ' kg')->alignEnd(),
                TextColumn::make('remitente.nombre_completo')->label('Remitente')->limit(28)->visibleFrom('md'),
                TextColumn::make('destinatario.nombre_completo')->label('Destinatario')->limit(28)->visibleFrom('lg'),
                TextColumn::make('manifiesto.numero')->label('Manifiesto')->formatStateUsing(fn ($state, RemesaCarga $r) => $r->manifiesto?->numeroFormateado())->placeholder('—')->visibleFrom('md'),
                TiemposDeRemesa::columna(),
                AccionesRndc::columnaEstado(),
            ])
            ->filters([
                SelectFilter::make('operacion')->options(RemesaCarga::OPERACIONES),
                SelectFilter::make('sin_manifiesto')->label('Sin manifiesto')->query(fn ($query, array $data) => $data['value'] ? $query->whereNull('manifiesto_id') : $query),
            ])
            ->recordActions([
                AccionesRndc::grupo(),
                EditAction::make()->iconButton()->visible(fn (RemesaCarga $r) => ! $r->manifiesto_id && ! $r->anulado()),
                Action::make('cumplir')->iconButton()->label('Cumplir / Tiempos')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (RemesaCarga $r) => auth()->user()?->can('crear_cumplidos_carga') && ! $r->anulado())
                    ->fillForm(fn (RemesaCarga $r) => [
                        'tipo' => $r->cumplido?->tipo ?? 'C',
                        'motivo_suspension' => $r->cumplido?->motivo_suspension,
                        'cantidad_entregada' => $r->cumplido?->cantidad_entregada ?? $r->cantidad,
                        // Si el conductor ya marcó los tiempos, no se le vuelven a pedir.
                        'llegada_descargue' => $r->cumplido?->llegada_descargue ?? TiemposLogisticos::registrados($r)['descargue.llegada']?->ocurrido_at,
                        'entrada_descargue' => $r->cumplido?->entrada_descargue ?? TiemposLogisticos::registrados($r)['descargue.entrada']?->ocurrido_at,
                        'salida_descargue' => $r->cumplido?->salida_descargue ?? TiemposLogisticos::registrados($r)['descargue.salida']?->ocurrido_at,
                        'observaciones' => $r->cumplido?->observaciones,
                    ])
                    ->schema([
                        Select::make('tipo')->options(CumplidoRemesa::TIPOS)->default('C')->required()->live(),
                        Select::make('motivo_suspension')->options(CumplidoRemesa::MOTIVOS_SUSPENSION)
                            ->visible(fn ($get) => $get('tipo') === 'S')->required(fn ($get) => $get('tipo') === 'S'),
                        DateTimePicker::make('llegada_descargue')->label('Llegada al descargue')->seconds(false)
                            ->required(fn ($get) => $get('tipo') !== 'S')->hidden(fn ($get) => $get('tipo') === 'S'),
                        DateTimePicker::make('entrada_descargue')->label('Entrada al descargue')->seconds(false)
                            ->required(fn ($get) => $get('tipo') !== 'S')->hidden(fn ($get) => $get('tipo') === 'S'),
                        DateTimePicker::make('salida_descargue')->label('Salida del descargue')->seconds(false)
                            ->required(fn ($get) => $get('tipo') !== 'S')->hidden(fn ($get) => $get('tipo') === 'S'),
                        TextInput::make('cantidad_entregada')->numeric()->minValue(0)
                            ->hidden(fn ($get) => $get('tipo') === 'S'),
                        Textarea::make('observaciones')->rows(2)->maxLength(200),
                    ])
                    ->action(function (RemesaCarga $r, array $data): void {
                        CumplidoRemesa::updateOrCreate(
                            ['remesa_id' => $r->id],
                            ['empresa_id' => $r->empresa_id, 'user_id' => auth()->id(), ...$data]
                        );
                        Notification::make()->success()->title('Cumplido guardado')->body('Envíe al RNDC desde la bitácora cuando esté todo listo.')->send();
                    }),
                TiemposDeRemesa::accion(),
                AnularConRndc::remesa(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarRemesas::route('/'),
            'crear' => CrearRemesa::route('/crear'),
            'editar' => EditarRemesa::route('/{record}/editar'),
        ];
    }
}
