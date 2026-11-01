<?php

namespace App\Filament\App\Resources\RutinasMantenimiento;

use App\Filament\App\Resources\RutinasMantenimiento\Pages\GestionarRutinas;
use App\Models\ClaseVehiculo;
use App\Models\RutinaMantenimiento;
use App\Models\Vehiculo;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Rutinas del plan preventivo: cada cuántos km y/o meses. Para toda la flota, una clase de vehículo
 * o un vehículo (la más específica reemplaza a la general con el mismo nombre).
 */
class RutinaMantenimientoResource extends Resource
{
    protected static ?string $model = RutinaMantenimiento::class;

    protected static ?string $slug = 'rutinas-preventivas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Rutinas preventivas';

    protected static ?string $breadcrumb = 'Rutinas preventivas';

    protected static ?string $modelLabel = 'rutina preventiva';

    protected static ?string $pluralModelLabel = 'rutinas preventivas';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('nombre')->required()->maxLength(255)->columnSpanFull()
                ->helperText('Para cambiar los intervalos de una clase o de un vehículo, cree una rutina con el mismo nombre y ese alcance.'),
            TextInput::make('intervalo_km')->label('Cada (km)')->numeric()->minValue(100)->maxValue(1000000)
                ->required(fn (Get $get) => blank($get('intervalo_meses'))),
            TextInput::make('intervalo_meses')->label('O cada (meses)')->numeric()->minValue(1)->maxValue(120)
                ->helperText('Lo que ocurra primero.'),
            Select::make('clase_vehiculo_id')->label('Solo para la clase')->placeholder('Todas')
                ->options(fn () => ClaseVehiculo::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                ->disabled(fn (Get $get) => filled($get('vehiculo_id'))),
            Select::make('vehiculo_id')->label('Solo para el vehículo')->placeholder('Ninguno')->searchable()
                ->options(fn () => Vehiculo::query()->enFlota()->orderBy('placa')->pluck('placa', 'id'))->live(),
            Textarea::make('descripcion')->rows(2)->columnSpanFull(),
            Toggle::make('activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['claseVehiculo', 'vehiculo']))
            ->defaultSort('orden')
            ->reorderable('orden')
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre')->weight('bold')->wrap()->searchable()
                    ->description(fn (RutinaMantenimiento $r) => $r->descripcion),
                TextColumn::make('frecuencia'),
                TextColumn::make('alcance')->badge()->color(fn (RutinaMantenimiento $r) => $r->vehiculo_id || $r->clase_vehiculo_id ? 'info' : 'gray'),
                ToggleColumn::make('activo')->disabled(fn () => ! auth()->user()?->can('editar_rutinas_mantenimiento')),
            ])
            ->filters([TernaryFilter::make('activo')->default(true)])
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data) => filled($data['vehiculo_id'] ?? null) ? [...$data, 'clase_vehiculo_id' => null] : $data),
                DeleteAction::make()->modalDescription('Los mantenimientos que ya la cumplieron la conservan. Si solo quiere dejar de usarla, desactívela.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => GestionarRutinas::route('/')];
    }
}
