<?php

namespace App\Filament\App\Resources\TercerosCarga;

use App\Filament\App\Carga\AccionesRndc;
use App\Filament\App\Resources\TercerosCarga\Pages\GestionarTercerosCarga;
use App\Filament\Compartido\CamposComunes;
use App\Models\TerceroCarga;
use App\Models\TipoIdentificacion;
use App\Support\Carga\CatalogoRndc;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Terceros de carga: remitentes, destinatarios, titulares de manifiesto y generadores. Los
 * propietarios, tenedores y conductores se registran desde Flota y conductores.
 */
class TerceroCargaResource extends Resource
{
    protected static ?string $model = TerceroCarga::class;

    protected static ?string $slug = 'terceros-carga';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|UnitEnum|null $navigationGroup = 'Carga';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Terceros';

    protected static ?string $modelLabel = 'tercero';

    protected static ?string $pluralModelLabel = 'terceros de carga';

    protected static ?string $tenantRelationshipName = 'tercerosCarga';

    private static function esNit(Get $get): bool
    {
        return TipoIdentificacion::query()->whereKey($get('tipo_identificacion_id'))->value('codigo') === 'NIT';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'md' => 2])->components([
            Select::make('tipo_identificacion_id')->label('Tipo de identificación')->required()->live()
                ->options(fn () => TipoIdentificacion::query()->activos()->whereIn('codigo', array_keys(CatalogoRndc::TIPOS_ID))->orderBy('nombre')->pluck('nombre', 'id')),
            TextInput::make('documento')->label('Número de identificación')->required()->maxLength(15)
                ->helperText(fn (Get $get) => self::esNit($get) ? 'NIT con el dígito de verificación, sin guion (máximo 10 dígitos).' : 'Solo números.'),
            TextInput::make('nombre')->label(fn (Get $get) => self::esNit($get) ? 'Razón social' : 'Nombres')->required()->maxLength(100),
            TextInput::make('primer_apellido')->label('Primer apellido')->maxLength(100)->required(fn (Get $get) => ! self::esNit($get))->hidden(fn (Get $get) => self::esNit($get)),
            TextInput::make('segundo_apellido')->label('Segundo apellido')->maxLength(100)->hidden(fn (Get $get) => self::esNit($get)),
            TextInput::make('sede_codigo')->label('Código de la sede')->default('1')->maxLength(6)->visible(fn (Get $get) => self::esNit($get))
                ->helperText('Una empresa puede tener varias sedes: registre cada sede con su código.'),
            TextInput::make('sede_nombre')->label('Nombre de la sede')->default('PRINCIPAL')->maxLength(20)->visible(fn (Get $get) => self::esNit($get)),
            TextInput::make('telefono')->label('Teléfono fijo')->tel()->maxLength(7)->helperText('7 dígitos, sin indicativo. Obligatorio para empresas.'),
            TextInput::make('celular')->label('Celular')->tel()->maxLength(10),
            TextInput::make('direccion')->label('Dirección')->required()->maxLength(60),
            CamposComunes::municipio()->required(),
            TextInput::make('email')->label('Correo electrónico')->email()->maxLength(255),
            CheckboxList::make('roles')->label('Rol en la carga')->options(CatalogoRndc::ROLES_TERCERO)->columns(2),
            Textarea::make('observaciones')->rows(2)->columnSpanFull(),
            Toggle::make('activo')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tipoIdentificacion', 'municipio.departamento', 'ultimoRegistroRndc']))
            ->defaultSort('nombre')
            ->emptyStateHeading('Registre sus remitentes y destinatarios')
            ->emptyStateDescription('El RNDC exige registrarlos antes de expedir remesas y manifiestos.')
            ->columns([
                TextColumn::make('nombre_completo')->label('Nombre')->weight('bold')->searchable(['nombre', 'primer_apellido', 'documento'])
                    ->description(fn (TerceroCarga $r) => trim(($r->tipoIdentificacion?->codigo ?? '') . ' ' . $r->documento) . ($r->esJuridica() ? ' · sede ' . $r->sede_codigo . ' ' . $r->sede_nombre : '')),
                TextColumn::make('roles')->label('Rol')->badge()->color('gray')->formatStateUsing(fn ($state) => CatalogoRndc::ROLES_TERCERO[$state] ?? $state),
                TextColumn::make('municipio.nombre_completo')->label('Ciudad')->visibleFrom('lg'),
                TextColumn::make('celular')->label('Contacto')->state(fn (TerceroCarga $r) => $r->celular ?: $r->telefono)->visibleFrom('md'),
                AccionesRndc::columnaEstado(),
            ])
            ->filters([
                SelectFilter::make('rol')->options(CatalogoRndc::ROLES_TERCERO)
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereJsonContains('roles', $data['value']) : $query),
                TernaryFilter::make('activo'),
            ])
            ->recordActions([AccionesRndc::grupo(), EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([AccionesRndc::fichasMasivas()])]);
    }

    public static function getPages(): array
    {
        return ['index' => GestionarTercerosCarga::route('/')];
    }
}
