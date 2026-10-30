<?php

namespace App\Filament\App\Resources\Vehiculos\RelationManagers;

use App\Models\NovedadVehiculo;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Historial de novedades (cambios de estado). Solo lectura: se registran con "Cambiar estado".
 */
class NovedadesRelationManager extends RelationManager
{
    protected static string $relationship = 'novedades';

    protected static ?string $title = 'Novedades';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clock';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', NovedadVehiculo::class);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('usuario'))
            ->defaultSort(fn ($query) => $query->orderByDesc('fecha')->orderByDesc('id'))
            ->emptyStateHeading('Sin novedades')
            ->emptyStateDescription('Use «Cambiar estado» para suspender, enviar a taller, desvincular o reactivar el vehículo.')
            ->columns([
                TextColumn::make('fecha')->date('d/m/Y')->weight('bold'),
                TextColumn::make('tipo')->label('Novedad')->badge(),
                TextColumn::make('estado_nuevo')->label('Queda')->badge()->visibleFrom('md'),
                TextColumn::make('fecha_reactivacion')->label('Reactivación prevista')->date('d/m/Y')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('observacion')->label('Observación')->wrap()->limit(120),
                TextColumn::make('usuario.name')->label('Registró')->placeholder('—')->visibleFrom('lg'),
            ]);
    }
}
