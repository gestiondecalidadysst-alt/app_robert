<?php

namespace App\Filament\App\Resources\Propietarios;

use App\Filament\App\Carga\AccionesRndc;
use App\Filament\App\Resources\Propietarios\Pages\GestionarPropietarios;
use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Filament\Compartido\CamposComunes;
use App\Models\Propietario;
use App\Models\TipoIdentificacion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
 * Propietarios (afiliados) de los vehículos de la empresa.
 */
class PropietarioResource extends Resource
{
    protected static ?string $model = Propietario::class;

    protected static ?string $slug = 'propietarios';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Flota y conductores';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'propietario';

    protected static ?string $pluralModelLabel = 'propietarios';

    protected static ?string $recordTitleAttribute = 'nombres';

    protected static ?string $tenantRelationshipName = 'propietarios';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->components(self::campos());
    }

    /** Campos del formulario; también se usan para crear un propietario desde el formulario del vehículo. */
    public static function campos(): array
    {
        return [
            Select::make('tipo_identificacion_id')
                ->label('Tipo de identificación')
                ->options(fn () => TipoIdentificacion::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                ->required(),
            TextInput::make('documento')
                ->label('Número de identificación')
                ->required()
                ->maxLength(20)
                ->scopedUnique(ignoreRecord: true)
                ->helperText('Sin puntos ni dígito de verificación.'),
            TextInput::make('nombres')
                ->label('Nombres o razón social')
                ->required()
                ->maxLength(255),
            TextInput::make('apellidos')->maxLength(255),
            TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
            TextInput::make('email')->label('Correo electrónico')->email()->maxLength(255),
            TextInput::make('direccion')->label('Dirección')->maxLength(255),
            CamposComunes::municipio(),
            DatePicker::make('fecha_nacimiento')->label('Fecha de nacimiento')->maxDate(now()),
            Select::make('genero')->label('Género')->options(Propietario::GENEROS),
            DatePicker::make('fecha_ingreso')->label('Fecha de ingreso')->default(now()),
            Toggle::make('activo')->default(true)->inline(false),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['municipio.departamento', 'tipoIdentificacion'])
                ->withCount(['vehiculos' => fn ($q) => $q->enFlota()]))
            ->defaultSort('nombres')
            ->columns([
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->weight('bold')
                    ->searchable(['nombres', 'apellidos'])
                    ->sortable(['nombres'])
                    ->description(fn (Propietario $record) => trim(($record->tipoIdentificacion?->codigo ?? '').' '.$record->documento)),
                TextColumn::make('documento')->label('Documento')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('telefono')->label('Teléfono')->visibleFrom('md'),
                TextColumn::make('email')->label('Correo')->toggleable()->visibleFrom('lg'),
                TextColumn::make('municipio.nombre_completo')->label('Ciudad')->toggleable()->visibleFrom('lg'),
                TextColumn::make('vehiculos_count')
                    ->label('Vehículos')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'primary' : 'gray')
                    ->sortable(),
                AccionesRndc::columnaEstado(),
                IconColumn::make('activo')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('activo'),
            ])
            ->toolbarActions([BulkActionGroup::make([AccionesRndc::fichasMasivas()])])
            ->recordActions([
                AccionesRndc::grupo(),
                Action::make('vehiculos')->iconButton()
                    ->label('Vehículos')
                    ->icon('heroicon-o-truck')
                    ->color('gray')
                    ->visible(fn (Propietario $record) => $record->vehiculos_count > 0)
                    ->url(fn (Propietario $record) => VehiculoResource::getUrl('index', [
                        'filters' => ['propietario_id' => ['value' => $record->getKey()]],
                    ])),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton()
                    ->before(function (Propietario $record, DeleteAction $action): void {
                        if ($record->vehiculos()->exists()) {
                            Notification::make()
                                ->title('No se puede eliminar')
                                ->body('El propietario tiene vehículos registrados. Desactívelo en su lugar.')
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => GestionarPropietarios::route('/'),
        ];
    }
}
