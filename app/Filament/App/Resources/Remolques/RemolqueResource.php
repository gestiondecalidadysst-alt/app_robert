<?php

namespace App\Filament\App\Resources\Remolques;

use App\Filament\App\Carga\AccionesRndc;
use App\Filament\App\Resources\Remolques\Pages\GestionarRemolques;
use App\Models\Propietario;
use App\Models\Remolque;
use App\Support\Carga\CatalogoRndc;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Semirremolques, remolques y remolques balanceados (placa R + 5 números). */
class RemolqueResource extends Resource
{
    protected static ?string $model = Remolque::class;

    protected static ?string $slug = 'remolques';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Carga';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Remolques';

    protected static ?string $modelLabel = 'remolque';

    protected static ?string $pluralModelLabel = 'remolques y semirremolques';

    protected static ?string $tenantRelationshipName = 'remolques';

    public static function form(Schema $schema): Schema
    {
        $propietarios = fn () => Propietario::query()->where('activo', true)->orderBy('nombres')->get()
            ->mapWithKeys(fn (Propietario $p) => [$p->id => $p->nombre_completo.' · '.$p->documento]);

        return $schema->components([
            Section::make('Identificación')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('placa')->label('Placa')->required()->maxLength(6)->placeholder('R12345')->regex('/^[Rr]\d{5}$/')
                        ->validationMessages(['regex' => 'La placa es R seguida de 5 números (por ejemplo R12345).'])
                        ->scopedUnique(ignoreRecord: true)
                        ->helperText('Semirremolques, remolques y remolques balanceados siempre arrancan por R.'),
                    TextInput::make('modelo')->label('Año del modelo')->required()->numeric()->minValue(1901)->maxValue((int) date('Y') + 1),
                    Select::make('configuracion')->label('Configuración RNDC')->required()->live()->native(false)
                        ->options(CatalogoRndc::CONFIGURACIONES_REMOLQUE)
                        ->helperText('Según el número de ejes y si es semirremolque, remolque o remolque balanceado.'),
                    TextInput::make('numero_ejes')->label('Número de ejes')->numeric()->minValue(1)->maxValue(20)
                        ->required(fn (Get $get) => in_array((int) $get('configuracion'), CatalogoRndc::CON_EJES, true))
                        ->visible(fn (Get $get) => in_array((int) $get('configuracion'), CatalogoRndc::CON_EJES, true)),
                    TextInput::make('marca_nombre')->label('Marca (nombre)')->maxLength(60)->placeholder('Dite, Furel, Trailstar…'),
                    TextInput::make('marca_codigo')->label('Código RNDC de la marca')->required()->maxLength(10)
                        ->helperText('Tabla «Marca Semirremolque y Remolque» del anexo del RNDC.'),
                    TextInput::make('carroceria_codigo')->label('Código RNDC de la carrocería')->maxLength(3)
                        ->helperText('Tabla «Tipos de Carrocería» del anexo.'),
                    Toggle::make('activo')->default(true)->inline(false),
                ]),
            Section::make('Medidas y capacidad')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('peso_vacio')->label('Peso vacío')->required()->numeric()->minValue(201)->maxValue(52999)->suffix('kg'),
                    TextInput::make('capacidad')->label('Capacidad')->numeric()->minValue(100)->maxValue(99999)
                        ->required(fn (Get $get) => in_array((int) $get('configuracion'), CatalogoRndc::CON_CAPACIDAD, true))
                        ->helperText('Obligatoria para semirremolques de más de 3 ejes, remolques de más de 4 ejes y balanceados de más de 4 ejes.'),
                    Select::make('unidad_capacidad')->label('Unidad de la capacidad')->options(CatalogoRndc::UNIDADES_CAPACIDAD)->default(1)->required()->native(false),
                ]),
            Section::make('Propietario y tenedor')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('propietario_id')->label('Propietario')->required()->searchable()->options($propietarios)->native(false),
                    Select::make('tenedor_id')->label('Tenedor (poseedor)')->searchable()->options($propietarios)->native(false)
                        ->helperText('En leasing, el propietario es la compañía de leasing y el tenedor quien lo usa. Si se deja vacío, el tenedor es el mismo propietario.'),
                ]),
            Section::make('Observaciones (opcional)')
                ->collapsed()
                ->schema([Textarea::make('observaciones')->rows(2)->hiddenLabel()]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['propietario', 'tenedor', 'ultimoRegistroRndc']))
            ->defaultSort('placa')
            ->emptyStateHeading('Registre sus remolques y semirremolques')
            ->columns([
                TextColumn::make('placa')->weight('bold')->searchable()
                    ->description(fn (Remolque $r) => CatalogoRndc::CONFIGURACIONES_REMOLQUE[$r->configuracion] ?? $r->configuracion),
                TextColumn::make('marca_nombre')->label('Marca')->placeholder('—')->description(fn (Remolque $r) => $r->modelo)->visibleFrom('md'),
                TextColumn::make('capacidad')->numeric(thousandsSeparator: '.')->suffix(fn (Remolque $r) => $r->unidad_capacidad === 2 ? ' gal' : ' kg')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('propietario.nombre_completo')->label('Propietario')->visibleFrom('lg'),
                AccionesRndc::columnaEstado(),
            ])
            ->filters([TernaryFilter::make('activo')])
            ->recordActions([AccionesRndc::grupo(), EditAction::make()->iconButton(), DeleteAction::make()->iconButton()])
            ->toolbarActions([BulkActionGroup::make([AccionesRndc::fichasMasivas()])]);
    }

    public static function getPages(): array
    {
        return ['index' => GestionarRemolques::route('/')];
    }
}
