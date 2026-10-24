<?php

namespace App\Filament\Admin\Resources\Aseguradoras;

use App\Filament\Admin\Resources\Aseguradoras\Pages\GestionarAseguradoras;
use App\Models\Aseguradora;
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
 * Catálogo nacional: aseguradoras. Los registros no se borran, se desactivan.
 */
class AseguradoraResource extends Resource
{
    protected static ?string $model = Aseguradora::class;

    protected static ?string $slug = 'aseguradoras';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|UnitEnum|null $navigationGroup = 'Vehículos';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'aseguradora';

    protected static ?string $pluralModelLabel = 'aseguradoras';

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
            'index' => GestionarAseguradoras::route('/'),
        ];
    }
}
