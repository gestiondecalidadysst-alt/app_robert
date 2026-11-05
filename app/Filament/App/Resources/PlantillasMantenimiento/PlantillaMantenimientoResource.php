<?php

namespace App\Filament\App\Resources\PlantillasMantenimiento;

use App\Actions\Mantenimiento\PlanDeVehiculo;
use App\Enums\CriterioPlantilla;
use App\Filament\App\Resources\PlantillasMantenimiento\Pages\CrearPlantilla;
use App\Filament\App\Resources\PlantillasMantenimiento\Pages\EditarPlantilla;
use App\Filament\App\Resources\PlantillasMantenimiento\Pages\ListarPlantillas;
use App\Models\ClaseVehiculo;
use App\Models\Linea;
use App\Models\Marca;
use App\Models\PlantillaMantenimiento;
use App\Models\Vehiculo;
use App\Support\Mantenimiento\PlanPreventivo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Plantillas de plan de mantenimiento preventivo: por clase de vehículo, por marca y línea, o libres.
 * Los vehículos siguen su plantilla (asignada o sugerida) salvo que tengan un plan personalizado.
 */
class PlantillaMantenimientoResource extends Resource
{
    protected static ?string $model = PlantillaMantenimiento::class;

    protected static ?string $slug = 'plantillas-mantenimiento';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Plantillas de plan';

    protected static ?string $breadcrumb = 'Plantillas de plan';

    protected static ?string $modelLabel = 'plantilla de plan';

    protected static ?string $pluralModelLabel = 'plantillas de plan';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Plantilla')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('nombre')->required()->maxLength(255)->columnSpan(['md' => 2])
                        ->placeholder('Ej.: Buseta diésel Hyundai County'),
                    Toggle::make('activo')->default(true)->inline(false),
                    ToggleButtons::make('criterio')->label('Se aplica')->options(CriterioPlantilla::class)
                        ->default(CriterioPlantilla::Clase->value)->inline()->required()->live()->columnSpanFull(),
                    Select::make('clase_vehiculo_id')->label('Clase de vehículo')
                        ->options(fn () => ClaseVehiculo::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->required(fn (Get $get) => self::criterio($get) === CriterioPlantilla::Clase)
                        ->visible(fn (Get $get) => self::criterio($get) === CriterioPlantilla::Clase),
                    Select::make('marca_id')->label('Marca')->searchable()->live()
                        ->options(fn () => Marca::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->afterStateUpdated(fn (Set $set) => $set('linea_id', null))
                        ->required(fn (Get $get) => self::criterio($get) === CriterioPlantilla::MarcaLinea)
                        ->visible(fn (Get $get) => self::criterio($get) === CriterioPlantilla::MarcaLinea),
                    Select::make('linea_id')->label('Línea')->searchable()->placeholder('Todas las líneas')
                        ->options(fn (Get $get) => $get('marca_id') ? Linea::query()->where('marca_id', $get('marca_id'))->activos()->orderBy('nombre')->pluck('nombre', 'id') : [])
                        ->visible(fn (Get $get) => self::criterio($get) === CriterioPlantilla::MarcaLinea),
                    Toggle::make('por_defecto')->label('Plantilla por defecto')->inline(false)
                        ->helperText('Aplica a los vehículos que no tienen otra plantilla. Solo puede haber una.'),
                    Textarea::make('descripcion')->rows(2)->columnSpanFull()
                        ->placeholder('Fuente de los intervalos (manual del fabricante, recomendación del taller…)'),
                ]),
            Section::make('Rutinas')
                ->description('Cada rutina se vence por km o por meses, lo que ocurra primero. Para dejar de usar una, desactívela (conserva su historial).')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('rutinas')
                        ->relationship('rutinas')
                        ->hiddenLabel()
                        ->minItems(1)
                        ->orderColumn('orden')
                        ->reorderable()
                        ->deletable(fn (string $operation) => $operation === 'create')
                        ->addActionLabel('Agregar rutina')
                        ->columns(['default' => 2, 'md' => 6])
                        ->itemLabel(fn (array $state) => $state['nombre'] ?? null)
                        ->collapsible()
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, $livewire) => [...$data, 'empresa_id' => Filament::getTenant()->id])
                        ->schema([
                            TextInput::make('nombre')->required()->maxLength(255)->columnSpan(['default' => 2, 'md' => 3]),
                            TextInput::make('intervalo_km')->label('Cada (km)')->numeric()->minValue(100)->maxValue(1000000)
                                ->required(fn (Get $get) => blank($get('intervalo_meses'))),
                            TextInput::make('intervalo_meses')->label('O cada (meses)')->numeric()->minValue(1)->maxValue(120),
                            Toggle::make('activo')->default(true)->inline(false),
                            TextInput::make('descripcion')->label('Detalle')->maxLength(255)->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    private static function criterio(Get $get): ?CriterioPlantilla
    {
        $valor = $get('criterio');

        return $valor instanceof CriterioPlantilla ? $valor : CriterioPlantilla::tryFrom((string) $valor);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['claseVehiculo', 'marca', 'linea'])
                ->withCount(['rutinas' => fn ($q) => $q->where('activo', true)]))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('por_defecto')->orderBy('nombre'))
            ->columns([
                TextColumn::make('nombre')->weight('bold')->searchable()->description(fn (PlantillaMantenimiento $r) => $r->descripcion),
                TextColumn::make('criterio')->label('Se aplica')->badge(),
                TextColumn::make('aplica_a')->label('A'),
                TextColumn::make('rutinas_count')->label('Rutinas'),
                TextColumn::make('vehiculos')->label('Vehículos')
                    ->state(fn (PlantillaMantenimiento $r) => self::vehiculosQueLaSiguen($r)->count()),
                IconColumn::make('por_defecto')->label('Por defecto')->boolean(),
                IconColumn::make('activo')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('aplicar')
                    ->label('Asignar a vehículos')
                    ->icon('heroicon-o-truck')
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->can('editar_rutinas_mantenimiento'))
                    ->schema([
                        Select::make('vehiculos')->label('Vehículos')->multiple()->searchable()->required()
                            ->options(fn () => Vehiculo::query()->enFlota()->orderBy('placa')->get()
                                ->mapWithKeys(fn (Vehiculo $v) => [$v->id => $v->placa.($v->plan_personalizado ? ' (plan personalizado)' : '')])),
                    ])
                    ->modalDescription('Los vehículos elegidos siguen esta plantilla. Si tenían un plan personalizado, se reemplaza (su historial se conserva).')
                    ->action(function (PlantillaMantenimiento $record, array $data): void {
                        Vehiculo::query()->whereKey($data['vehiculos'])->get()->each(fn (Vehiculo $v) => PlanDeVehiculo::seguirPlantilla($v, $record));
                        Notification::make()->success()->title(count($data['vehiculos']).' vehículo(s) siguen la plantilla «'.$record->nombre.'»')->send();
                    }),
                Action::make('duplicar')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->can('crear_rutinas_mantenimiento'))
                    ->action(function (PlantillaMantenimiento $record, $livewire): void {
                        $copia = $record->replicate(['por_defecto']);
                        $copia->nombre = $record->nombre.' (copia)';
                        $copia->por_defecto = false;
                        $copia->save();
                        foreach ($record->rutinas as $r) {
                            $nueva = $r->replicate();
                            $nueva->plantilla_mantenimiento_id = $copia->id;
                            $nueva->save();
                        }
                        $livewire->redirect(self::getUrl('edit', ['record' => $copia]));
                    }),
            ]);
    }

    /** Vehículos de la flota cuyo plan sale de esta plantilla (asignada o sugerida, sin personalizar). */
    public static function vehiculosQueLaSiguen(PlantillaMantenimiento $plantilla)
    {
        return Vehiculo::query()->enFlota()->where('plan_personalizado', false)->get()
            ->filter(fn (Vehiculo $v) => PlanPreventivo::plantillaDe($v)?->id === $plantilla->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarPlantillas::route('/'),
            'create' => CrearPlantilla::route('/crear'),
            'edit' => EditarPlantilla::route('/{record}/editar'),
        ];
    }
}
