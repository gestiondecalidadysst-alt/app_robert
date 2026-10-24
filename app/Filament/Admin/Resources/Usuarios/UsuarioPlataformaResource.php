<?php

namespace App\Filament\Admin\Resources\Usuarios;

use App\Filament\Admin\Resources\Usuarios\Pages\GestionarUsuarios;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Todos los usuarios de la plataforma. Los roles por empresa se asignan dentro de cada empresa.
 */
class UsuarioPlataformaResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'usuarios';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Nombre completo')->required()->maxLength(255),
                TextInput::make('email')->label('Correo electrónico')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('documento')->label('Documento')->maxLength(20),
                TextInput::make('telefono')->label('Teléfono')->maxLength(30),
                TextInput::make('password')
                    ->label(fn (string $operation) => $operation === 'create' ? 'Contraseña' : 'Nueva contraseña')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state)),
                Toggle::make('activo')->default(true)->inline(false),
                Toggle::make('es_superadmin')
                    ->label('Superadministrador de la plataforma')
                    ->helperText('Acceso total a todas las empresas y al panel de plataforma.')
                    ->disabled(fn (?User $record) => $record?->is(auth()->user())),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('empresas'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable()->weight('bold')
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('email')->label('Correo')->searchable()->hidden(),
                TextColumn::make('empresas.razon_social')->label('Empresas')->badge()->limitList(3),
                IconColumn::make('es_superadmin')->label('Superadmin')->boolean(),
                IconColumn::make('activo')->boolean(),
                TextColumn::make('ultimo_acceso_at')->label('Último acceso')->since()->placeholder('Nunca')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('es_superadmin')->label('Superadministrador'),
                TernaryFilter::make('activo'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => GestionarUsuarios::route('/'),
        ];
    }
}
