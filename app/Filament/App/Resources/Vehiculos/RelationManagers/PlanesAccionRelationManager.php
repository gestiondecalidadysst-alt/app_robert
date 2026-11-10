<?php

namespace App\Filament\App\Resources\Vehiculos\RelationManagers;

use App\Filament\App\Resources\PlanesAccion\PlanAccionResource;
use App\Models\PlanAccion;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PlanesAccionRelationManager extends RelationManager
{
    protected static string $relationship = 'planesAccion';

    protected static ?string $title = 'Planes de acción';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clipboard-document-list';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', PlanAccion::class);
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = PlanAccion::query()->where('vehiculo_id', $ownerRecord->id)->pendientes()->count();

        return $n ? (string) $n : null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Sin planes de acción')
            ->recordUrl(fn (PlanAccion $r) => PlanAccionResource::getUrl('view', ['record' => $r]))
            ->columns([
                TextColumn::make('numero')->label('No.')->weight('bold')->formatStateUsing(fn (PlanAccion $record) => $record->numero_formateado),
                TextColumn::make('titulo')->label('Hallazgo')->wrap()->limit(80),
                TextColumn::make('prioridad')->badge(),
                TextColumn::make('estado')->badge(),
                TextColumn::make('fecha_limite')->label('Límite')->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('pendientes')->label('Pendientes')->default(true)
                    ->queries(true: fn (Builder $query) => $query->pendientes(), false: fn (Builder $query) => $query->where('estado', 'cerrado')),
            ]);
    }
}
