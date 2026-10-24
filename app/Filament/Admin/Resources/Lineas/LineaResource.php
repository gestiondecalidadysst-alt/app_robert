<?php

namespace App\Filament\Admin\Resources\Lineas;

use App\Filament\Admin\Resources\Lineas\Pages\GestionarLineas;
use App\Models\Linea;
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
 * Catálogo nacional: líneas. Los registros no se borran, se desactivan.
 */
class LineaResource extends Resource
{
    protected static ?string $model = Linea::class;

    protected static ?string $slug = 'lineas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|UnitEnum|null $navigationGroup = 'Vehículos';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'línea';

    protected static ?string $pluralModelLabel = 'líneas';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('marca_id')->label('Marca')->relationship('marca', 'nombre')->searchable()->preload()->required(),
                TextInput::make('nombre')->required()->maxLength(255),
                Toggle::make('activo')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('marca.nombre')->label('Marca')->sortable()->searchable(),
                TextColumn::make('nombre')->label('Línea')->sortable()->searchable()->weight('bold'),
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
            'index' => GestionarLineas::route('/'),
        ];
    }
}
