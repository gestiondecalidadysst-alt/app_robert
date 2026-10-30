<?php

namespace App\Filament\App\Resources\Conductores\RelationManagers;

use App\Actions\Conductores\AsignarVehiculo;
use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Models\AsignacionVehiculo;
use App\Models\Vehiculo;
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
 * Vehículos que maneja el conductor (asignaciones vigentes e historial).
 */
class VehiculosRelationManager extends RelationManager
{
    protected static string $relationship = 'asignaciones';

    protected static ?string $title = 'Vehículos';

    protected static ?string $modelLabel = 'asignación';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-truck';

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('vehiculo.marca', 'vehiculo.linea'))
            ->defaultSort(fn ($query) => $query->orderByRaw('fecha_hasta is not null')->orderByDesc('fecha_desde'))
            ->recordUrl(fn (AsignacionVehiculo $r) => $r->vehiculo && ! $r->vehiculo->trashed() ? VehiculoResource::getUrl('view', ['record' => $r->vehiculo]) : null)
            ->emptyStateHeading('Sin vehículos asignados')
            ->columns(AsignacionColumnas::comunes('vehiculo'))
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
                    ->label('Asignar vehículo')
                    ->modalHeading('Asignar vehículo')
                    ->schema([
                        Select::make('vehiculo_id')
                            ->label('Vehículo')
                            ->options(fn () => Vehiculo::query()->enFlota()->orderBy('placa')->get()
                                ->mapWithKeys(fn (Vehiculo $v) => [$v->id => trim("{$v->placa} · {$v->descripcion}")]))
                            ->searchable()
                            ->required(),
                        DatePicker::make('fecha_desde')->label('Desde')->default(now())->required(),
                        Toggle::make('principal')->label('Conductor principal del vehículo'),
                        TextInput::make('observacion')->label('Observación')->maxLength(255),
                    ])
                    ->using(fn (array $data) => app(AsignarVehiculo::class)(
                        $this->getOwnerRecord(),
                        Vehiculo::findOrFail($data['vehiculo_id']),
                        Carbon::parse($data['fecha_desde']),
                        (bool) ($data['principal'] ?? false),
                        $data['observacion'] ?? null,
                    ))
                    ->after(fn () => $this->dispatch('documentos-actualizados')),
            ])
            ->recordActions([AsignacionColumnas::terminar(fn () => $this->dispatch('documentos-actualizados'))]);
    }
}
