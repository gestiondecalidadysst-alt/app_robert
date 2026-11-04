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
            ->mapWithKeys(fn (Propietario $p) => [$p->id => $p->nombre_completo . ' · ' . $p->documento]);

        return $schema->columns(['default' => 1, 'md' => 3])->components([
            TextInput::make('placa')->required()->maxLength(6)->placeholder('R12345')->regex('/^[Rr]\d{5}$/')
                ->validationMessages(['regex' => 'La placa es R seguida de 5 números.'])->scopedUnique(ignoreRecord: true),
            Select::make('configuracion')->label('Configuración')->required()->live()->options(CatalogoRndc::CONFIGURACIONES_REMOLQUE),
            TextInput::make('numero_ejes')->label('Número de ejes')->numeric()->minValue(1)->maxValue(20)
                ->required(fn (Get $get) => in_array((int) $get('configuracion'), CatalogoRndc::CON_EJES, true))
                ->visible(fn (Get $get) => in_array((int) $get('configuracion'), CatalogoRndc::CON_EJES, true)),
            TextInput::make('marca_codigo')->label('Código RNDC de la marca')->required()->maxLength(10)
                ->helperText('Tabla «Marca Semirremolque y Remolque» del anexo del RNDC.'),
            TextInput::make('marca_nombre')->label('Marca')->maxLength(60),
            TextInput::make('modelo')->label('Año del modelo')->required()->numeric()->minValue(1901)->maxValue((int) date('Y') + 1),
            TextInput::make('peso_vacio')->label('Peso vacío (kg)')->required()->numeric()->minValue(201)->maxValue(52999),
            TextInput::make('capacidad')->label('Capacidad')->numeric()->minValue(100)->maxValue(99999)
                ->required(fn (Get $get) => in_array((int) $get('configuracion'), CatalogoRndc::CON_CAPACIDAD, true)),
            Select::make('unidad_capacidad')->label('Unidad de la capacidad')->options(CatalogoRndc::UNIDADES_CAPACIDAD)->default(1)->required(),
            TextInput::make('carroceria_codigo')->label('Código RNDC del tipo de carrocería')->maxLength(3),
            Select::make('propietario_id')->label('Propietario')->required()->searchable()->options($propietarios),
            Select::make('tenedor_id')->label('Tenedor (poseedor)')->searchable()->options($propietarios)
                ->helperText('Vacío = el mismo propietario. En leasing, el propietario es la compañía de leasing y el tenedor quien lo usa.'),
            Textarea::make('observaciones')->rows(2)->columnSpanFull(),
            Toggle::make('activo')->default(true)->inline(false),
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
            ->recordActions([AccionesRndc::grupo(), EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([AccionesRndc::fichasMasivas()])]);
    }

    public static function getPages(): array
    {
        return ['index' => GestionarRemolques::route('/')];
    }
}
