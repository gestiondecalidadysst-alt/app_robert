<?php

namespace App\Filament\Admin\Resources\Municipios;

use App\Filament\Admin\Resources\Municipios\Pages\GestionarMunicipios;
use App\Models\Municipio;
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
 * Catálogo nacional: municipios. Los registros no se borran, se desactivan.
 */
class MunicipioResource extends Resource
{
    protected static ?string $model = Municipio::class;

    protected static ?string $slug = 'municipios';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup = 'Ubicación';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'municipio';

    protected static ?string $pluralModelLabel = 'municipios';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('departamento_id')->label('Departamento')->relationship('departamento', 'nombre')->searchable()->preload()->required(),
                TextInput::make('codigo_dane')->label('Código DANE')->required()->length(5)->unique(ignoreRecord: true),
                TextInput::make('nombre')->required()->maxLength(255),
                Toggle::make('activo')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('codigo_dane')->label('Código')->sortable()->searchable(),
                TextColumn::make('nombre')->sortable()->searchable()->weight('bold'),
                TextColumn::make('departamento.nombre')->label('Departamento')->sortable()->searchable(),
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
            'index' => GestionarMunicipios::route('/'),
        ];
    }
}
