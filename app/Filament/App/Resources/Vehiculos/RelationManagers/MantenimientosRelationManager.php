<?php

namespace App\Filament\App\Resources\Vehiculos\RelationManagers;

use App\Filament\App\Resources\Mantenimientos\MantenimientoResource;
use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Models\Mantenimiento;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan preventivo (semáforo por rutina) e historial de mantenimientos del vehículo.
 */
class MantenimientosRelationManager extends RelationManager
{
    protected static string $relationship = 'mantenimientos';

    protected static ?string $title = 'Mantenimiento';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-wrench-screwdriver';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', Mantenimiento::class);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('rutinas')->withCount('evidencias'))
            ->defaultSort(fn ($query) => $query->orderByDesc('fecha')->orderByDesc('id'))
            ->header(fn () => view('filament.mantenimiento.plan-preventivo-vehiculo', ['vehiculo' => $this->getOwnerRecord()]))
            ->emptyStateHeading('Sin mantenimientos registrados')
            ->headerActions([
                Action::make('plan')->label('Plan de mantenimiento')->icon('heroicon-o-adjustments-horizontal')->color('gray')
                    ->visible(fn () => auth()->user()?->can('ver_rutinas_mantenimiento'))
                    ->url(fn () => VehiculoResource::getUrl('plan-mantenimiento', ['record' => $this->getOwnerRecord()])),
                Action::make('registrar')->label('Registrar mantenimiento')->icon('heroicon-o-plus')
                    ->visible(fn () => auth()->user()?->can('crear_mantenimientos'))
                    ->url(fn () => MantenimientoResource::getUrl('create', ['vehiculo' => $this->getOwnerRecord()->id])),
            ])
            ->recordUrl(fn (Mantenimiento $r) => MantenimientoResource::getUrl('view', ['record' => $r]))
            ->columns([
                TextColumn::make('fecha')->date('d/m/Y')->weight('bold')
                    ->description(fn (Mantenimiento $r) => $r->anulado() ? 'Anulado' : ($r->registro_inicial ? 'Punto de partida' : null)),
                TextColumn::make('tipo')->badge(),
                TextColumn::make('actividad')->wrap()->limit(80),
                TextColumn::make('rutinas.nombre')->label('Rutinas')->badge()->visibleFrom('lg'),
                TextColumn::make('kilometraje')->numeric(thousandsSeparator: '.')->placeholder('—'),
                TextColumn::make('valor')->money('COP', locale: 'es_CO')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('evidencias_count')->label('Evid.')->badge()->color(fn (int $state) => $state ? 'success' : 'gray'),
            ]);
    }
}
