<?php

namespace App\Filament\Admin\Resources\CategoriasLicencia;

use App\Filament\Admin\Resources\CategoriasLicencia\Pages\GestionarCategoriasLicencia;
use App\Models\CategoriaLicencia;
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
 * Catálogo nacional: categorías de licencia. Los registros no se borran, se desactivan.
 */
class CategoriaLicenciaResource extends Resource
{
    protected static ?string $model = CategoriaLicencia::class;

    protected static ?string $slug = 'categorias-licencia';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Personas';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'categoría de licencia';

    protected static ?string $pluralModelLabel = 'categorías de licencia';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('codigo')->label('Código')->required()->maxLength(5)->unique(ignoreRecord: true),
                TextInput::make('descripcion')->label('Descripción')->required()->maxLength(255),
                Toggle::make('activo')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('codigo')
            ->columns([
                TextColumn::make('codigo')->label('Código')->sortable()->searchable()->weight('bold'),
                TextColumn::make('descripcion')->label('Descripción')->searchable(),
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
            'index' => GestionarCategoriasLicencia::route('/'),
        ];
    }
}
