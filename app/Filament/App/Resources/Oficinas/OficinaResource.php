<?php

namespace App\Filament\App\Resources\Oficinas;

use App\Filament\App\Resources\Oficinas\Pages\ManageOficinas;
use App\Filament\Compartido\CamposComunes;
use App\Models\Oficina;
use BackedEnum;
use Filament\Actions\DeleteAction;
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

class OficinaResource extends Resource
{
    protected static ?string $model = Oficina::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'oficina';

    protected static ?string $pluralModelLabel = 'oficinas';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255)
                    ->scopedUnique(ignoreRecord: true),
                CamposComunes::municipio(),
                TextInput::make('direccion')->label('Dirección')->maxLength(255),
                TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                Toggle::make('activa')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('municipio.departamento'))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable()->weight('bold'),
                TextColumn::make('municipio.nombre_completo')->label('Municipio'),
                TextColumn::make('direccion')->label('Dirección')->toggleable(),
                TextColumn::make('telefono')->label('Teléfono'),
                IconColumn::make('activa')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('activa'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOficinas::route('/'),
        ];
    }
}
