<?php

namespace App\Filament\Admin\Resources\Manual;

use App\Filament\Admin\Resources\Manual\Pages\CrearSeccionManual;
use App\Filament\Admin\Resources\Manual\Pages\EditarSeccionManual;
use App\Filament\Admin\Resources\Manual\Pages\ListarSeccionesManual;
use App\Models\SeccionManual;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Edición del manual de la aplicación (secciones). No aparece en el menú: se edita desde la
 * página «Manual», que es la vista de lectura. Solo plataforma (superadministrador).
 */
class SeccionManualResource extends Resource
{
    protected static ?string $model = SeccionManual::class;

    protected static ?string $slug = 'manual-secciones';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?string $modelLabel = 'sección del manual';

    protected static ?string $pluralModelLabel = 'secciones del manual';

    public static function shouldRegisterNavigation(): bool
    {
        return false; // El ítem del menú es la página «Manual» (lectura).
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('grupo')
                    ->label('Grupo (sección del índice)')
                    ->required()
                    ->datalist(fn () => SeccionManual::query()->distinct()->orderBy('grupo')->pluck('grupo')->all())
                    ->helperText('Agrupa la sección en el índice, p. ej. «Operación», «Flota», «Plataforma».'),
                TextInput::make('titulo')->label('Título')->required()->maxLength(255),
                TextInput::make('orden')->label('Orden dentro del grupo')->numeric()->default(0),
                TextInput::make('icono')->label('Ícono (opcional)')->placeholder('heroicon-o-truck')
                    ->helperText('Nombre de un ícono heroicon; opcional.'),
                RichEditor::make('contenido')
                    ->label('Contenido')
                    ->columnSpanFull()
                    ->toolbarButtons([
                        'bold', 'italic', 'strike', 'link',
                        'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'undo', 'redo',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('grupo')
            ->reorderable('orden')
            ->columns([
                TextColumn::make('grupo')->label('Grupo')->badge()->color('gray')->sortable(),
                TextColumn::make('titulo')->label('Título')->searchable()->sortable()->weight('medium'),
                TextColumn::make('orden')->label('Orden')->sortable()->toggleable(),
                TextColumn::make('updated_at')->label('Actualizada')->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarSeccionesManual::route('/'),
            'create' => CrearSeccionManual::route('/crear'),
            'edit' => EditarSeccionManual::route('/{record}/editar'),
        ];
    }
}
