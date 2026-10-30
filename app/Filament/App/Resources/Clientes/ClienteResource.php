<?php

namespace App\Filament\App\Resources\Clientes;

use App\Filament\App\Resources\Clientes\Pages\GestionarClientes;
use App\Filament\App\Resources\Contratos\ContratoResource;
use App\Filament\Compartido\CamposComunes;
use App\Models\Cliente;
use App\Models\TipoIdentificacion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Clientes (contratantes) de la empresa.
 */
class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $slug = 'clientes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $tenantRelationshipName = 'clientes';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components(self::campos());
    }

    /** Campos del cliente; también se usan para crearlo desde el formulario del contrato. */
    public static function campos(): array
    {
        return [
            Select::make('tipo_identificacion_id')
                ->label('Tipo de identificación')
                ->options(fn () => TipoIdentificacion::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                ->default(fn () => TipoIdentificacion::firstWhere('codigo', 'NIT')?->id)
                ->required(),
            TextInput::make('documento')
                ->label('NIT o cédula')
                ->required()
                ->maxLength(20)
                ->scopedUnique(ignoreRecord: true)
                ->helperText('Sin puntos ni dígito de verificación (se calcula solo).'),
            TextInput::make('nombre')->label('Razón social o nombre completo')->required()->maxLength(255)->columnSpanFull(),
            CamposComunes::municipio('expedicion_municipio_id', 'Cédula expedida en'),
            TextInput::make('contacto')->label('Contacto o representante')->maxLength(255),
            TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
            TextInput::make('email')->label('Correo electrónico')->email()->maxLength(255),
            TextInput::make('direccion')->label('Dirección')->maxLength(255),
            CamposComunes::municipio(),
            Toggle::make('activo')->default(true)->inline(false),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tipoIdentificacion', 'municipio.departamento'])->withCount('contratos'))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')
                    ->label('Cliente')
                    ->weight('bold')
                    ->searchable(['nombre', 'documento'])
                    ->sortable()
                    ->description(fn (Cliente $record) => $record->identificacion),
                TextColumn::make('contacto')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('telefono')->label('Teléfono')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('municipio.nombre_completo')->label('Ciudad')->placeholder('—')->toggleable()->visibleFrom('lg'),
                TextColumn::make('contratos_count')->label('Contratos')->badge()->color(fn (int $state) => $state ? 'primary' : 'gray')->sortable(),
                IconColumn::make('activo')->boolean(),
            ])
            ->filters([TernaryFilter::make('activo')])
            ->recordActions([
                Action::make('contratos')
                    ->label('Contratos')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn (Cliente $record) => $record->contratos_count > 0)
                    ->url(fn (Cliente $record) => ContratoResource::getUrl('index', ['filters' => ['cliente_id' => ['value' => $record->getKey()]]])),
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Cliente $record, DeleteAction $action): void {
                        if ($record->contratos()->exists()) {
                            Notification::make()->title('No se puede eliminar')
                                ->body('El cliente tiene contratos. Desactívelo en su lugar.')->danger()->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => GestionarClientes::route('/')];
    }
}
