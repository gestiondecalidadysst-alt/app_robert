<?php

namespace App\Filament\Admin\Resources\TiposIdentificacion;

use App\Filament\Admin\Resources\TiposIdentificacion\Pages\GestionarTiposIdentificacion;
use App\Models\TipoIdentificacion;
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
 * Catálogo nacional: tipos de identificación. Los registros no se borran, se desactivan.
 */
class TipoIdentificacionResource extends Resource
{
    protected static ?string $model = TipoIdentificacion::class;

    protected static ?string $slug = 'tipos-identificacion';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Personas';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'tipo de identificación';

    protected static ?string $pluralModelLabel = 'tipos de identificación';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('codigo')->label('Código')->required()->maxLength(5)->unique(ignoreRecord: true),
                TextInput::make('nombre')->required()->maxLength(255),
                Toggle::make('es_empresa')->label('Es de persona jurídica'),
                Toggle::make('activo')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('codigo')->label('Código')->sortable()->searchable(),
                TextColumn::make('nombre')->sortable()->searchable()->weight('bold'),
                IconColumn::make('es_empresa')->label('Persona jurídica')->boolean(),
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
            'index' => GestionarTiposIdentificacion::route('/'),
        ];
    }
}
