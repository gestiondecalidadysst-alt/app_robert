<?php

namespace App\Filament\Admin\Resources\Empresas;

use App\Filament\Admin\Resources\Empresas\Pages\CrearEmpresa;
use App\Filament\Admin\Resources\Empresas\Pages\EditarEmpresa;
use App\Filament\Admin\Resources\Empresas\Pages\ListarEmpresas;
use App\Filament\Compartido\FormularioEmpresa;
use App\Models\Empresa;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Empresas clientes de la plataforma (solo superadministrador).
 */
class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'empresa';

    protected static ?string $pluralModelLabel = 'empresas';

    protected static ?string $recordTitleAttribute = 'razon_social';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...FormularioEmpresa::componentes(),

            Section::make('Primer administrador')
                ->columnSpanFull()
                ->description('Usuario que podrá entrar y configurar la empresa. Si el correo ya existe, se vincula ese usuario.')
                ->icon('heroicon-o-user-plus')
                ->columns(['default' => 1, 'md' => 3])
                ->visibleOn('create')
                ->schema([
                    TextInput::make('administrador.name')->label('Nombre completo')->required(),
                    TextInput::make('administrador.email')->label('Correo electrónico')->email()->required(),
                    TextInput::make('administrador.password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->required(),
                ]),

            Section::make('Consultas de tránsito (comparendos y RUNT)')
                ->columnSpanFull()
                ->visibleOn('edit')
                ->icon('heroicon-o-magnifying-glass-circle')
                ->description('Si se habilita, la empresa puede consultar comparendos, licencias y RUNT con la cuenta de la plataforma y se le cobra el precio por consulta. Deje vacío un precio para usar el general (Plataforma → Consultas de tránsito).')
                ->columns(['default' => 1, 'md' => 5])
                ->schema([
                    Toggle::make('transito_habilitado')->label('Módulo habilitado')->inline(false),
                    ...collect(\App\Enums\TipoConsultaTransito::cases())->map(fn (\App\Enums\TipoConsultaTransito $t) => TextInput::make("transito_precios.{$t->value}")
                        ->label('Precio especial · ' . $t->getLabel())->numeric()->minValue(0)->prefix('$')
                        ->placeholder(fn () => ($general = \App\Models\ProveedorTransito::actual()->precioDe($t)) !== null ? number_format($general, 0, ',', '.') . ' (general)' : 'Sin precio general'))->all(),
                ]),

            Section::make('Estado')
                ->columnSpanFull()
                ->visibleOn('edit')
                ->schema([
                    Toggle::make('activa')
                        ->helperText('Una empresa inactiva bloquea el acceso de todos sus usuarios.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('municipio.departamento')->withCount('usuarios'))
            ->defaultSort('razon_social')
            ->columns([
                ImageColumn::make('logo_path')->label('')->disk('public')->imageSize(36),
                TextColumn::make('razon_social')
                    ->label('Empresa')
                    ->searchable(['razon_social', 'nombre_comercial', 'nit'])
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Empresa $record) => 'NIT ' . $record->nit_formateado),
                TextColumn::make('municipio.nombre_completo')->label('Municipio'),
                TextColumn::make('usuarios_count')->label('Usuarios')->badge(),
                TextColumn::make('slug')->label('Dirección')->prefix('/app/')->color('gray')->toggleable(),
                IconColumn::make('activa')->boolean(),
                TextColumn::make('created_at')->label('Alta')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('activa'),
            ])
            ->recordActions([
                Action::make('entrar')
                    ->label('Entrar')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Empresa $record) => url("/app/{$record->slug}")),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarEmpresas::route('/'),
            'create' => CrearEmpresa::route('/crear'),
            'edit' => EditarEmpresa::route('/{record}/editar'),
        ];
    }
}
