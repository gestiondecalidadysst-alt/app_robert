<?php

namespace App\Filament\Admin\Resources\NivelesAcademicos;

use App\Filament\Admin\Resources\NivelesAcademicos\Pages\GestionarNivelesAcademicos;
use App\Models\NivelAcademico;
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
 * Catálogo nacional: niveles académicos. Los registros no se borran, se desactivan.
 */
class NivelAcademicoResource extends Resource
{
    protected static ?string $model = NivelAcademico::class;

    protected static ?string $slug = 'niveles-academicos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'Personas';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'nivel académico';

    protected static ?string $pluralModelLabel = 'niveles académicos';

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
            'index' => GestionarNivelesAcademicos::route('/'),
        ];
    }
}
