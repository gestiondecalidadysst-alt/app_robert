<?php

namespace App\Filament\App\Resources\Vehiculos\RelationManagers;

use App\Filament\Compartido\ErroresDeNegocio;
use App\Actions\Conductores\AsignarVehiculo;
use App\Filament\App\Resources\Conductores\ConductorResource;
use App\Filament\App\Resources\Conductores\RelationManagers\AsignacionColumnas;
use App\Models\AsignacionVehiculo;
use App\Models\Conductor;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Conductores asignados al vehículo (vigentes e historial).
 */
class ConductoresRelationManager extends RelationManager
{
    protected static string $relationship = 'asignaciones';

    protected static ?string $title = 'Conductores';

    protected static ?string $modelLabel = 'asignación';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-user-group';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', AsignacionVehiculo::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('conductor'))
            ->defaultSort(fn ($query) => $query->orderByRaw('fecha_hasta is not null')->orderByDesc('principal')->orderByDesc('fecha_desde'))
            ->recordUrl(fn (AsignacionVehiculo $r) => $r->conductor && ! $r->conductor->trashed() ? ConductorResource::getUrl('view', ['record' => $r->conductor]) : null)
            ->emptyStateHeading('Sin conductores asignados')
            ->columns(AsignacionColumnas::comunes('conductor'))
            ->filters([
                TernaryFilter::make('vigentes')
                    ->label('Mostrar')
                    ->placeholder('Todas')
                    ->trueLabel('Solo vigentes')
                    ->falseLabel('Solo terminadas')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('fecha_hasta'),
                        false: fn (Builder $q) => $q->whereNotNull('fecha_hasta'),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Asignar conductor')
                    ->modalHeading('Asignar conductor')
                    ->schema([
                        Select::make('conductor_id')
                            ->label('Conductor')
                            ->options(fn () => Conductor::query()->vinculados()->orderBy('nombres')->get()
                                ->mapWithKeys(fn (Conductor $c) => [$c->id => "{$c->nombre_completo} · {$c->documento}"]))
                            ->searchable()
                            ->required(),
                        DatePicker::make('fecha_desde')->label('Desde')->default(now())->required(),
                        Toggle::make('principal')->label('Conductor principal del vehículo'),
                        TextInput::make('observacion')->label('Observación')->maxLength(255),
                    ])
                    ->using(fn (array $data, CreateAction $action) => ErroresDeNegocio::intentar($action, fn () => app(AsignarVehiculo::class)(
                        Conductor::findOrFail($data['conductor_id']),
                        $this->getOwnerRecord(),
                        Carbon::parse($data['fecha_desde']),
                        (bool) ($data['principal'] ?? false),
                        $data['observacion'] ?? null,
                    ))),
            ])
            ->recordActions([AsignacionColumnas::terminar()]);
    }
}
