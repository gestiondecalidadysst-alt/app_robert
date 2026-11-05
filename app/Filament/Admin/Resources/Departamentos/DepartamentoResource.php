<?php

namespace App\Filament\Admin\Resources\Departamentos;

use App\Filament\Admin\Resources\Departamentos\Pages\GestionarDepartamentos;
use App\Models\Departamento;
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
 * Catálogo nacional: departamentos. Los registros no se borran, se desactivan.
 */
class DepartamentoResource extends Resource
{
    protected static ?string $model = Departamento::class;

    protected static ?string $slug = 'departamentos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|UnitEnum|null $navigationGroup = 'Ubicación';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'departamento';

    protected static ?string $pluralModelLabel = 'departamentos';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('codigo_dane')->label('Código DANE')->required()->length(2)->unique(ignoreRecord: true),
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
                TextColumn::make('municipios_count')->label('Municipios')->counts('municipios')->badge(),
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
            'index' => GestionarDepartamentos::route('/'),
        ];
    }
}
