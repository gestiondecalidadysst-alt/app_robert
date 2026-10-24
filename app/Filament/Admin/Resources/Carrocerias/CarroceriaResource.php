<?php

namespace App\Filament\Admin\Resources\Carrocerias;

use App\Filament\Admin\Resources\Carrocerias\Pages\GestionarCarrocerias;
use App\Models\Carroceria;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
 * Catálogo nacional: carrocerías. Los registros no se borran, se desactivan.
 */
class CarroceriaResource extends Resource
{
    protected static ?string $model = Carroceria::class;

    protected static ?string $slug = 'carrocerias';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = 'Vehículos';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'carrocería';

    protected static ?string $pluralModelLabel = 'carrocerías';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('nombre')->required()->maxLength(255)->unique(ignoreRecord: true),
                Toggle::make('activo')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->sortable()->searchable()->weight('bold'),
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
            'index' => GestionarCarrocerias::route('/'),
        ];
    }
}
