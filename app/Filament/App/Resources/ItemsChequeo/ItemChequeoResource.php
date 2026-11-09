<?php

namespace App\Filament\App\Resources\ItemsChequeo;

use App\Enums\TipoInspeccion;
use App\Filament\App\Resources\ItemsChequeo\Pages\ManageItemsChequeo;
use App\Models\ItemChequeo;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Ítems de las listas de chequeo (preoperacional e inspección mensual) de la empresa.
 * Cada inspección guarda su copia: cambiar o desactivar un ítem no altera las anteriores.
 */
class ItemChequeoResource extends Resource
{
    protected static ?string $model = ItemChequeo::class;

    protected static ?string $slug = 'items-chequeo';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Seguridad vial';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Ítems de chequeo';

    protected static ?string $modelLabel = 'ítem de chequeo';

    protected static ?string $pluralModelLabel = 'ítems de chequeo';

    protected static ?string $recordTitleAttribute = 'descripcion';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('tipo')->options(TipoInspeccion::class)->required()
                    ->default(fn ($livewire) => $livewire->activeTab ?? TipoInspeccion::Preoperacional->value)
                    ->disabledOn('edit'),
                TextInput::make('grupo')->required()->maxLength(60)
                    ->datalist(fn () => ItemChequeo::query()->distinct()->orderBy('grupo')->pluck('grupo')->all())
                    ->helperText('Ej.: Luces y señales, Llantas, Documentos.'),
                TextInput::make('descripcion')->label('Qué se revisa')->required()->maxLength(255)->columnSpanFull(),
                Toggle::make('critico')->label('Crítico')
                    ->helperText('Si no cumple, el vehículo queda NO APTO y no puede operar ese día.'),
                Toggle::make('activo')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->paginated(false)
            ->defaultGroup('grupo')
            ->columns([
                TextColumn::make('descripcion')->label('Qué se revisa')->searchable()->wrap(),
                TextColumn::make('grupo')->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('critico')->label('Crítico')
                    ->disabled(fn () => ! auth()->user()?->can('editar_items_chequeo')),
                ToggleColumn::make('activo')
                    ->disabled(fn () => ! auth()->user()?->can('editar_items_chequeo')),
            ])
            ->filters([
                TernaryFilter::make('activo')->default(true),
                SelectFilter::make('critico')->label('Crítico')->options([1 => 'Sí', 0 => 'No']),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton()->modalDescription('Las inspecciones ya registradas conservan este ítem. Si solo quiere dejar de pedirlo, desactívelo.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageItemsChequeo::route('/'),
        ];
    }
}
