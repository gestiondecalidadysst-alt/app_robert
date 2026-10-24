<?php

namespace App\Filament\App\Resources\Roles;

use App\Filament\App\Resources\Roles\Pages\CrearRol;
use App\Filament\App\Resources\Roles\Pages\EditarRol;
use App\Filament\App\Resources\Roles\Pages\ListarRoles;
use App\Models\Rol;
use App\Support\Permisos;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Roles de la empresa y su matriz de permisos.
 */
class RolResource extends Resource
{
    protected static ?string $model = Rol::class;

    protected static ?string $slug = 'roles';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'rol';

    protected static ?string $pluralModelLabel = 'roles y permisos';

    protected static ?string $navigationLabel = 'Roles y permisos';

    protected static ?string $breadcrumb = 'Roles y permisos';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantRelationshipName = 'roles';

    /** Nombre del campo del formulario que agrupa los permisos de un recurso. */
    public static function campoPermisos(string $grupo): string
    {
        return 'permisos_' . Str::slug($grupo, '_');
    }

    public static function form(Schema $schema): Schema
    {
        $secciones = [];
        foreach (Permisos::agrupados() as $grupo => $opciones) {
            $secciones[] = Section::make(Str::after($grupo, ' · '))
                ->description(Str::before($grupo, ' · '))
                ->compact()
                ->schema([
                    CheckboxList::make(static::campoPermisos($grupo))
                        ->hiddenLabel()
                        ->options($opciones)
                        ->columns(['default' => 2, 'sm' => 4])
                        ->bulkToggleable(),
                ]);
        }

        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre del rol')
                        ->required()
                        ->maxLength(100)
                        ->disabled(fn (?Rol $record) => $record?->name === Rol::ADMINISTRADOR)
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule) => $rule->where('empresa_id', Filament::getTenant()?->getKey()),
                        ),
                ]),
            Section::make('Permisos')
                ->columnSpanFull()
                ->description('Marque lo que pueden hacer los usuarios con este rol.')
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 2])->schema($secciones),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['permissions', 'users']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Rol')->weight('bold')->searchable(),
                TextColumn::make('users_count')->label('Usuarios')->badge(),
                TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->formatStateUsing(fn (int $state) => $state . ' de ' . count(Permisos::todos())),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Rol $record) => $record->name === Rol::ADMINISTRADOR || $record->users_count > 0),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarRoles::route('/'),
            'create' => CrearRol::route('/crear'),
            'edit' => EditarRol::route('/{record}/editar'),
        ];
    }
}
