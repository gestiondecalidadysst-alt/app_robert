<?php

namespace App\Filament\Admin\Resources\ClasesVehiculo;

use App\Filament\Admin\Resources\ClasesVehiculo\Pages\GestionarClasesVehiculo;
use App\Models\ClaseVehiculo;
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
 * Catálogo nacional: clases de vehículo. Los registros no se borran, se desactivan.
 */
class ClaseVehiculoResource extends Resource
{
    protected static ?string $model = ClaseVehiculo::class;

    protected static ?string $slug = 'clases-vehiculo';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Vehículos';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'clase de vehículo';

    protected static ?string $pluralModelLabel = 'clases de vehículo';

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
            'index' => GestionarClasesVehiculo::route('/'),
        ];
    }
}
