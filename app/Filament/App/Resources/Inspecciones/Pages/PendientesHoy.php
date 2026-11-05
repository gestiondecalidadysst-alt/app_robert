<?php

namespace App\Filament\App\Resources\Inspecciones\Pages;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoInspeccion;
use App\Filament\App\Resources\Inspecciones\InspeccionResource;
use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Models\AsignacionVehiculo;
use App\Models\Fuec;
use App\Models\Inspeccion;
use App\Models\Vehiculo;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vehículos operando sin un preoperacional APTO hoy (los que tienen FUEC vigente, primero).
 * Reemplaza "No realizadas" de la app anterior.
 */
class PendientesHoy extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = InspeccionResource::class;

    protected string $view = 'filament.inspecciones.pendientes';

    protected static ?string $title = 'Sin preoperacional hoy';

    public function getSubheading(): ?string
    {
        return 'Vehículos en estado «Operando» que hoy no tienen un preoperacional apto. El conductor lo hace desde su portal.';
    }

    public static function consulta(): Builder
    {
        $aptosHoy = Inspeccion::query()->select('vehiculo_id')
            ->where('tipo', TipoInspeccion::Preoperacional->value)
            ->whereDate('fecha', today())
            ->where('resultado', 'apto');

        return Vehiculo::query()
            ->where('estado', EstadoVehiculo::Operando->value)
            ->whereNotIn('id', $aptosHoy);
    }

    public static function cantidad(): int
    {
        return self::consulta()->count();
    }

    public function table(Table $table): Table
    {
        $conFuecHoy = Fuec::query()->select('vehiculo_id')->whereNull('anulado_at')
            ->whereDate('fecha_inicio', '<=', today())->whereDate('fecha_fin', '>=', today());

        return $table
            ->query(fn () => self::consulta()
                ->addSelect(['con_fuec' => Fuec::query()->selectRaw('count(*)')->whereColumn('fuecs.vehiculo_id', 'vehiculos.id')
                    ->whereNull('anulado_at')->whereDate('fecha_inicio', '<=', today())->whereDate('fecha_fin', '>=', today())]))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('con_fuec')->orderBy('placa'))
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('placa')->weight('bold')->searchable()
                    ->url(fn (Vehiculo $r) => VehiculoResource::getUrl('view', ['record' => $r])),
                TextColumn::make('numero_interno')->label('No. interno')->placeholder('—'),
                TextColumn::make('con_fuec')->label('Servicio hoy')
                    ->formatStateUsing(fn ($state) => $state ? 'Con FUEC vigente' : 'Sin FUEC')
                    ->badge()->color(fn ($state) => $state ? 'danger' : 'gray'),
                TextColumn::make('preop_hoy')->label('Hoy')
                    ->state(fn (Vehiculo $r) => $r->preoperacionalDelDia() ? 'No apto' : 'Sin hacer')
                    ->badge()->color(fn (string $state) => $state === 'No apto' ? 'danger' : 'warning'),
                TextColumn::make('conductor')->label('Conductor asignado')
                    ->state(fn (Vehiculo $r) => AsignacionVehiculo::query()->vigentes()->where('vehiculo_id', $r->id)
                        ->with('conductor')->orderByDesc('principal')->get()->map(fn ($a) => $a->conductor?->nombre_completo)->filter()->implode(', '))
                    ->placeholder('—'),
                TextColumn::make('ultimo')->label('Último preoperacional')
                    ->state(fn (Vehiculo $r) => Inspeccion::query()->preoperacionales()->where('vehiculo_id', $r->id)->latest('id')->first()?->created_at?->format('d/m/Y h:i a'))
                    ->placeholder('Nunca'),
            ])
            ->filters([
                Filter::make('con_servicio')->label('Solo con FUEC vigente hoy')
                    ->query(fn (Builder $query) => $query->whereIn('vehiculos.id', $conFuecHoy)),
            ]);
    }
}
