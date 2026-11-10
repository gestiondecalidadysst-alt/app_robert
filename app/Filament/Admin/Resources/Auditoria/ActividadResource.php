<?php

namespace App\Filament\Admin\Resources\Auditoria;

use App\Filament\Admin\Resources\Auditoria\Pages\ListarAuditoria;
use App\Filament\App\Resources\Auditoria\ActividadResource as AuditoriaEmpresa;
use App\Models\Actividad;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Auditoría global de la plataforma (solo lectura): quién creó, modificó o eliminó información
 * y cuándo, en TODAS las empresas. Cada empresa conserva su propia auditoría en su panel.
 */
class ActividadResource extends Resource
{
    protected static ?string $model = Actividad::class;

    protected static ?string $slug = 'auditoria';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'registro de auditoría';

    protected static ?string $pluralModelLabel = 'auditoría';

    protected static ?string $navigationLabel = 'Auditoría';

    protected static ?string $breadcrumb = 'Auditoría';

    /** Ve la actividad de todas las empresas: el panel de plataforma no tiene empresa activa. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope('empresa')
            ->with(['causer', 'empresa']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(4)->schema([
                TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i a'),
                TextEntry::make('empresa.razon_social')->label('Empresa')->placeholder('—'),
                TextEntry::make('causer.name')->label('Usuario')->placeholder('Sistema'),
                TextEntry::make('evento_legible')->label('Acción')->badge(),
            ]),
            TextEntry::make('cambios')
                ->label('Cambios')
                ->columnSpanFull()
                ->state(fn (Actividad $record) => AuditoriaEmpresa::resumenCambios($record))
                ->html(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i a')->sortable(),
                TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('causer.name')->label('Usuario')->placeholder('Sistema')->searchable(),
                TextColumn::make('evento_legible')
                    ->label('Acción')
                    ->badge()
                    ->color(fn (Actividad $record) => match ($record->event) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('log_name')
                    ->label('Registro')
                    ->formatStateUsing(fn (?string $state) => AuditoriaEmpresa::REGISTROS[$state] ?? $state),
                TextColumn::make('subject_id')->label('ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('empresa')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('log_name')->label('Registro')->options(AuditoriaEmpresa::REGISTROS),
                SelectFilter::make('event')->label('Acción')->options([
                    'created' => 'Creó',
                    'updated' => 'Modificó',
                    'deleted' => 'Eliminó',
                ]),
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['hasta'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarAuditoria::route('/'),
        ];
    }
}
