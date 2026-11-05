<?php

namespace App\Filament\Admin\Resources\Eps;

use App\Filament\Admin\Resources\Eps\Pages\GestionarEps;
use App\Models\Eps;
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
 * Catálogo nacional: EPS. Los registros no se borran, se desactivan.
 */
class EpsResource extends Resource
{
    protected static ?string $model = Eps::class;

    protected static ?string $slug = 'eps';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|UnitEnum|null $navigationGroup = 'Personas';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'EPS';

    protected static ?string $pluralModelLabel = 'EPS';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('nombre')->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('nit')->label('NIT')->maxLength(15),
                TextInput::make('telefono')->label('Teléfono')->maxLength(30),
                Toggle::make('activo')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->sortable()->searchable()->weight('bold'),
                TextColumn::make('nit')->label('NIT')->searchable(),
                TextColumn::make('telefono')->label('Teléfono'),
                IconColumn::make('activo')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('activo'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => GestionarEps::route('/'),
        ];
    }
}
