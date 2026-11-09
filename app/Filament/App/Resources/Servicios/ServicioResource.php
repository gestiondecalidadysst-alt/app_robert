<?php

namespace App\Filament\App\Resources\Servicios;

use App\Enums\EstadoServicio;
use App\Filament\App\Resources\Servicios\Pages\CrearServicio;
use App\Filament\App\Resources\Servicios\Pages\EditarServicio;
use App\Filament\App\Resources\Servicios\Pages\ListarServicios;
use App\Filament\App\Resources\Servicios\Pages\VerServicio;
use App\Filament\App\Resources\Servicios\Schemas\ServicioForm;
use App\Models\Servicio;
use App\Models\Vehiculo;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Servicios planificados (PESV paso 15). El tablero está en App\Filament\App\Pages\Planeador;
 * aquí, el listado, el detalle con el seguimiento y el formulario.
 */
class ServicioResource extends Resource
{
    protected static ?string $model = Servicio::class;

    protected static ?string $slug = 'servicios';

    protected static ?string $tenantRelationshipName = 'servicios';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Servicios';

    protected static ?string $modelLabel = 'servicio';

    protected static ?string $pluralModelLabel = 'servicios';

    public static function form(Schema $schema): Schema
    {
        return ServicioForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return Schemas\ServicioInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehiculo', 'conductores', 'origen', 'destino', 'fuec', 'ruta', 'contrato.cliente']))
            ->defaultSort(fn (Builder $query) => $query->orderBy('fecha_inicio')->orderBy('hora_salida'))
            ->emptyStateHeading('Sin servicios')
            ->emptyStateDescription('Planifique un servicio o expida un FUEC: todo extracto aparece aquí y en el planeador.')
            ->columns([
                TextColumn::make('fecha_inicio')->label('Fecha')->weight('bold')
                    ->formatStateUsing(fn (Servicio $record) => $record->fecha_inicio->format('d/m/Y').($record->fecha_fin->gt($record->fecha_inicio) ? ' – '.$record->fecha_fin->format('d/m/Y') : ''))
                    ->description(fn (Servicio $record) => $record->franja),
                TextColumn::make('ruta_texto')->label('Origen → destino')
                    ->description(fn (Servicio $record) => $record->ruta?->nombre ? 'Rutograma: '.$record->ruta->nombre : 'Sin rutograma'),
                TextColumn::make('vehiculo.placa')->label('Vehículo')->placeholder('Sin asignar')->badge()->color('gray'),
                TextColumn::make('conductores.nombres')->label('Conductores')->placeholder('Sin asignar')->visibleFrom('md')
                    ->formatStateUsing(fn (Servicio $record) => $record->conductores->map->nombre_completo->implode(', ')),
                TextColumn::make('estado')->badge(),
                TextColumn::make('fuec.numero')->label('Extracto')->placeholder('—')->visibleFrom('lg')
                    ->formatStateUsing(fn (Servicio $record) => $record->fuec?->numero_formateado),
                IconColumn::make('leido_at')->label('Plan leído')->boolean()->state(fn (Servicio $record) => (bool) $record->leido_at)->visibleFrom('lg'),
                TextColumn::make('contrato.cliente.nombre')->label('Cliente')->placeholder('—')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('estado')->options(EstadoServicio::class)->multiple(),
                SelectFilter::make('vehiculo_id')->label('Vehículo')->options(fn () => Vehiculo::query()->enFlota()->orderBy('placa')->pluck('placa', 'id'))->searchable(),
                Filter::make('fechas')
                    ->schema([
                        DatePicker::make('desde')->default(today()),
                        DatePicker::make('hasta'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $d) => $q->whereDate('fecha_fin', '>=', $d))
                        ->when($data['hasta'] ?? null, fn ($q, $d) => $q->whereDate('fecha_inicio', '<=', $d)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['desde'] ?? null) ? 'Desde '.Carbon::parse($data['desde'])->format('d/m/Y') : null,
                        ($data['hasta'] ?? null) ? 'Hasta '.Carbon::parse($data['hasta'])->format('d/m/Y') : null,
                    ])),
            ])
            ->recordActions([ViewAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarServicios::route('/'),
            'create' => CrearServicio::route('/planificar'),
            'view' => VerServicio::route('/{record}'),
            'edit' => EditarServicio::route('/{record}/editar'),
        ];
    }
}
