<?php

namespace App\Filament\App\Resources\Usuarios;

use App\Actions\Configuracion\AsignarRol;
use App\Filament\App\Resources\Usuarios\Pages\CrearUsuario;
use App\Filament\App\Resources\Usuarios\Pages\EditarUsuario;
use App\Filament\App\Resources\Usuarios\Pages\ListarUsuarios;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\Oficina;
use App\Models\Rol;
use App\Models\TipoIdentificacion;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;
use UnitEnum;

/**
 * Usuarios de la empresa activa. Un mismo usuario (correo) puede pertenecer a varias empresas,
 * con un rol y una oficina distintos en cada una.
 */
class UsuarioResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'usuarios';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantOwnershipRelationshipName = 'empresas';

    protected static ?string $tenantRelationshipName = 'usuarios';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Acceso en esta empresa')
                ->columnSpanFull()
                ->icon('heroicon-o-key')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('rol_id')
                        ->label('Rol')
                        ->options(fn () => Rol::query()->orderBy('name')->pluck('name', 'id'))
                        ->helperText('Con el rol Conductor puede elegir el conductor y darle acceso al portal del conductor.')
                        ->live()
                        ->required(),
                    Select::make('oficina_id')
                        ->label('Oficina')
                        ->options(fn () => Oficina::query()->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')),
                    Toggle::make('acceso_activo')
                        ->label('Acceso activo')
                        ->helperText('Si se desactiva, el usuario no puede entrar a esta empresa.')
                        ->default(true)
                        ->disabled(fn (?Model $record) => $record?->is(auth()->user())),
                    TextInput::make('password')
                        ->label(fn (string $operation) => $operation === 'create' ? 'Contraseña' : 'Nueva contraseña')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->required(fn (string $operation, Get $get) => $operation === 'create' && ! self::esRolConductor($get('rol_id')))
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->helperText(fn (string $operation, Get $get) => match (true) {
                            self::esRolConductor($get('rol_id')) => 'Clave temporal del portal: el conductor la cambia al entrar. Déjela vacía para que el sistema genere una' . ($operation === 'edit' ? ' solo si quiere restablecerla.' : '.'),
                            $operation === 'create' => 'Mínimo 8 caracteres. Si el usuario ya existía, conserva su contraseña actual.',
                            default => 'Déjela vacía para no cambiarla.',
                        }),
                ]),

            Section::make('Conductor')
                ->columnSpanFull()
                ->icon('heroicon-o-identification')
                ->description('El conductor entra al portal del conductor (/conductor) con su número de documento, no al panel de la empresa.')
                ->visible(fn (Get $get) => self::esRolConductor($get('rol_id')))
                ->schema([
                    Select::make('conductor_id')
                        ->label('Conductor')
                        ->helperText('Elija el conductor ya registrado en Conductores. Sus datos se copian abajo.')
                        ->options(fn (string $operation) => Conductor::query()->vinculados()->orderBy('nombres')->get()
                            ->mapWithKeys(fn (Conductor $c) => [$c->id => $c->nombre_completo . ' · ' . $c->documento . ($c->user_id && $operation === 'create' ? ' (ya tiene acceso)' : '')]))
                        ->searchable()
                        ->required(fn (Get $get) => self::esRolConductor($get('rol_id')))
                        ->disabledOn('edit')
                        ->dehydrated(fn (string $operation) => $operation === 'create')
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            $c = $state ? Conductor::query()->find($state) : null;
                            if ($c) {
                                $set('name', $c->nombre_completo);
                                $set('tipo_identificacion_id', $c->tipo_identificacion_id);
                                $set('documento', $c->documento);
                                $set('telefono', $c->telefono);
                                $set('email', $c->email);
                                $set('oficina_id', $c->oficina_id);
                            }
                        }),
                ]),

            Section::make('Datos personales')
                ->columnSpanFull()
                ->icon('heroicon-o-user')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('name')->label('Nombre completo')->required()->maxLength(255),
                    TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->required(fn (Get $get) => ! self::esRolConductor($get('rol_id')))
                        ->maxLength(255)
                        ->rules(fn (string $operation, ?Model $record): array => $operation === 'edit'
                            ? [Rule::unique('users', 'email')->ignore($record?->getKey())]
                            : [])
                        ->helperText(fn (string $operation, Get $get) => match (true) {
                            self::esRolConductor($get('rol_id')) => 'Opcional para conductores: entran al portal con su número de documento.',
                            $operation === 'create' => 'Si el correo ya existe en la plataforma (por ejemplo, en otra empresa), se vincula ese mismo usuario.',
                            default => null,
                        }),
                    Select::make('tipo_identificacion_id')
                        ->label('Tipo de identificación')
                        ->options(fn () => TipoIdentificacion::query()->activos()->where('es_empresa', false)->orderBy('nombre')->pluck('nombre', 'id')),
                    TextInput::make('documento')->label('Número de identificación')->maxLength(20),
                    TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                    FileUpload::make('avatar_path')
                        ->label('Foto')
                        ->image()
                        ->avatar()
                        ->disk('public')
                        ->directory('avatares')
                        ->maxSize(1024),
                ]),

        ]);
    }

    /** ¿El rol elegido es Conductor? (usa el portal del conductor, no el panel). */
    public static function esRolConductor(mixed $rolId): bool
    {
        return filled($rolId) && Rol::query()->whereKey($rolId)->value('name') === Rol::CONDUCTOR;
    }

    public static function table(Table $table): Table
    {
        $empresa = Filament::getTenant();
        $oficinas = Oficina::query()->pluck('nombre', 'id');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'roles',
                'empresas' => fn ($q) => $q->whereKey($empresa?->getKey()),
            ]))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('avatar_path')->label('')->disk('public')->circular()->imageSize(36),
                TextColumn::make('name')->label('Nombre')->searchable()->sortable()->weight('bold')
                    ->description(fn (User $record) => $record->email ?: 'Portal del conductor · ' . $record->documento),
                TextColumn::make('documento')->label('Documento')->searchable()->toggleable()->visibleFrom('lg'),
                TextColumn::make('roles.name')->label('Rol')->badge()->color('primary'),
                TextColumn::make('oficina')
                    ->label('Oficina')
                    ->visibleFrom('md')
                    ->state(fn (User $record) => $oficinas[$record->empresas->first()?->pivot?->oficina_id] ?? '—'),
                IconColumn::make('acceso_activo')
                    ->label('Acceso')
                    ->boolean()
                    ->state(fn (User $record) => (bool) $record->empresas->first()?->pivot?->activo && $record->activo),
                TextColumn::make('ultimo_acceso_at')->label('Último acceso')->since()->sortable()->placeholder('Nunca')->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('rol')
                    ->label('Rol')
                    ->options(fn () => Rol::query()->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereHas('roles', fn ($q) => $q->whereKey($data['value']))
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('quitar')
                    ->label('Quitar de la empresa')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('El usuario deja de tener acceso a esta empresa. Si pertenece a otras empresas, conserva ese acceso.')
                    ->authorize(fn (User $record) => auth()->user()->can('delete', $record))
                    ->action(function (User $record): void {
                        /** @var Empresa $empresa */
                        $empresa = Filament::getTenant();
                        (new AsignarRol)($record, $empresa, null);
                        $empresa->usuarios()->detach($record->getKey());
                        // Si era conductor de esta empresa, pierde el acceso al portal para ella.
                        Conductor::query()->deEmpresa($empresa)->where('user_id', $record->getKey())->update(['user_id' => null]);
                        app(PermissionRegistrar::class)->setPermissionsTeamId($empresa->getKey());

                        Notification::make()->title('Usuario retirado de la empresa')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarUsuarios::route('/'),
            'create' => CrearUsuario::route('/crear'),
            'edit' => EditarUsuario::route('/{record}/editar'),
        ];
    }
}
