<?php

namespace App\Filament\Admin\Resources\Arl;

use App\Filament\Admin\Resources\Arl\Pages\GestionarArl;
use App\Models\Arl;
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
 * Catálogo nacional: ARL. Los registros no se borran, se desactivan.
 */
class ArlResource extends Resource
{
    protected static ?string $model = Arl::class;

    protected static ?string $slug = 'arl';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static string|UnitEnum|null $navigationGroup = 'Personas';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'ARL';

    protected static ?string $pluralModelLabel = 'ARL';

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
            'index' => GestionarArl::route('/'),
        ];
    }
}
