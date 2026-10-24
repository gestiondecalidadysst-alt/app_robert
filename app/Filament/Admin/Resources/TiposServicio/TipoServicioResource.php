<?php

namespace App\Filament\Admin\Resources\TiposServicio;

use App\Filament\Admin\Resources\TiposServicio\Pages\GestionarTiposServicio;
use App\Models\TipoServicio;
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
 * Catálogo nacional: tipos de servicio. Los registros no se borran, se desactivan.
 */
class TipoServicioResource extends Resource
{
    protected static ?string $model = TipoServicio::class;

    protected static ?string $slug = 'tipos-servicio';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|UnitEnum|null $navigationGroup = 'Vehículos';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'tipo de servicio';

    protected static ?string $pluralModelLabel = 'tipos de servicio';

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
            'index' => GestionarTiposServicio::route('/'),
        ];
    }
}
