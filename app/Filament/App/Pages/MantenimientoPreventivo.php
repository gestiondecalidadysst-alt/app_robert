<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Models\Vehiculo;
use App\Support\Mantenimiento\PlanPreventivo;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Semáforo del mantenimiento preventivo de la flota: kilometraje actual y la rutina más urgente de
 * cada vehículo. El detalle por rutina está en la hoja de vida del vehículo.
 */
class MantenimientoPreventivo extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.inspecciones.pendientes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Plan preventivo';

    protected static ?string $title = 'Plan de mantenimiento preventivo';

    protected static ?string $slug = 'plan-preventivo';

    /** @var array<int, \Illuminate\Support\Collection> */
    private array $planes = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ver_mantenimientos') ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Kilometraje tomado del último preoperacional, inspección o mantenimiento. Amarillo: faltan menos de '
            . number_format(PlanPreventivo::AVISO_KM, 0, ',', '.') . ' km o ' . PlanPreventivo::AVISO_DIAS . ' días.';
    }

    private function plan(Vehiculo $v): \Illuminate\Support\Collection
    {
        return $this->planes[$v->id] ??= PlanPreventivo::para($v);
    }

    private function urgente(Vehiculo $v): ?array
    {
        $orden = [PlanPreventivo::VENCIDO => 0, PlanPreventivo::PROXIMO => 1, PlanPreventivo::AL_DIA => 2, PlanPreventivo::SIN_REGISTRO => 3];

        return $this->plan($v)->sortBy(fn ($f) => [$orden[$f['estado']], $f['faltan_km'] ?? PHP_INT_MAX])->first();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Vehiculo::query()->enFlota())
            ->defaultSort('placa')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('placa')->weight('bold')->searchable()
                    ->url(fn (Vehiculo $r) => VehiculoResource::getUrl('view', ['record' => $r])),
                TextColumn::make('numero_interno')->label('No. interno')->placeholder('—'),
                TextColumn::make('km')->label('Km actual')
                    ->state(fn (Vehiculo $r) => $this->plan($r)->first()['km_actual'] ?? $r->kilometrajeActual())
                    ->numeric(thousandsSeparator: '.')->placeholder('Sin reporte'),
                TextColumn::make('estado_preventivo')->label('Estado')->badge()
                    ->state(fn (Vehiculo $r) => PlanPreventivo::ETIQUETAS[PlanPreventivo::resumen($this->plan($r))])
                    ->color(fn (Vehiculo $r) => PlanPreventivo::COLORES[PlanPreventivo::resumen($this->plan($r))]),
                TextColumn::make('urgente')->label('Rutina más próxima')->wrap()
                    ->state(fn (Vehiculo $r) => ($f = $this->urgente($r)) ? $f['rutina']->nombre : null)
                    ->description(fn (Vehiculo $r) => ($f = $this->urgente($r)) && $f['estado'] !== PlanPreventivo::SIN_REGISTRO ? PlanPreventivo::textoFaltante($f) : 'Registre el último mantenimiento de cada rutina.')
                    ->placeholder('—'),
                TextColumn::make('vencidas')->label('Vencidas')
                    ->state(fn (Vehiculo $r) => $this->plan($r)->where('estado', PlanPreventivo::VENCIDO)->count())
                    ->color(fn (int $state) => $state ? 'danger' : 'gray'),
                TextColumn::make('sin_registro')->label('Sin registro')
                    ->state(fn (Vehiculo $r) => $this->plan($r)->where('estado', PlanPreventivo::SIN_REGISTRO)->count())
                    ->color('gray')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('clase_vehiculo_id')->label('Clase')->relationship('claseVehiculo', 'nombre'),
            ]);
    }
}
