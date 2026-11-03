<?php

namespace App\Filament\App\Resources\Vehiculos\Pages;

use App\Actions\Mantenimiento\PlanDeVehiculo;
use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\PlantillaMantenimiento;
use App\Models\Vehiculo;
use App\Support\Mantenimiento\PlanPreventivo;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/**
 * Plan de mantenimiento del vehículo: seguir una plantilla o personalizarlo (intervalos, rutinas
 * propias, copiar de otro vehículo) y su punto de partida (última vez que se hizo cada rutina).
 */
class PlanMantenimientoVehiculo extends Page
{
    use InteractsWithRecord;

    protected static string $resource = VehiculoResource::class;

    protected string $view = 'filament.mantenimiento.plan-vehiculo';

    public ?array $plan = [];

    public ?array $partida = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(auth()->user()?->can('ver_rutinas_mantenimiento'), 403);
        $this->llenar();
    }

    private function vehiculo(): Vehiculo
    {
        return $this->getRecord()->refresh();
    }

    private function puedeEditar(): bool
    {
        return auth()->user()?->can('editar_rutinas_mantenimiento') ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Plan de mantenimiento · ' . $this->getRecord()->placa;
    }

    public function getSubheading(): ?string
    {
        $v = $this->vehiculo();
        $plantilla = PlanPreventivo::plantillaDe($v);

        return $v->plan_personalizado
            ? 'Plan personalizado' . ($plantilla ? " (basado en «{$plantilla->nombre}»)" : '') . '. Los cambios de las plantillas no lo afectan.'
            : 'Sigue la plantilla «' . ($plantilla?->nombre ?? 'ninguna') . '»' . ($v->plantilla_mantenimiento_id ? ' (asignada).' : ' (sugerida por sus datos).');
    }

    private function llenar(): void
    {
        $v = $this->vehiculo();
        $plan = PlanPreventivo::para($v);

        $this->planForm->fill([
            'modo' => $v->plan_personalizado ? 'personalizado' : 'plantilla',
            'plantilla_mantenimiento_id' => PlanPreventivo::plantillaDe($v)?->id,
            'rutinas' => $plan->map(fn ($f) => [
                'id' => $f['rutina']->vehiculo_id === $v->id ? $f['rutina']->id : null,
                'nombre' => $f['rutina']->nombre,
                'intervalo_km' => $f['rutina']->intervalo_km,
                'intervalo_meses' => $f['rutina']->intervalo_meses,
                'descripcion' => $f['rutina']->descripcion,
            ])->values()->all(),
        ]);

        $this->partidaForm->fill(['puntos' => $plan->mapWithKeys(fn ($f) => [$f['rutina']->id => ['fecha' => null, 'kilometraje' => null]])->all()]);
    }

    protected function getForms(): array
    {
        return ['planForm', 'partidaForm'];
    }

    public function planForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('plan')
            ->disabled(! $this->puedeEditar())
            ->components([
                Section::make('Plan')
                    ->schema([
                        ToggleButtons::make('modo')->hiddenLabel()->inline()->live()
                            ->options(['plantilla' => 'Seguir una plantilla', 'personalizado' => 'Personalizar para este vehículo']),
                        Select::make('plantilla_mantenimiento_id')->label('Plantilla')
                            ->options(fn () => PlantillaMantenimiento::query()->activas()->orderByDesc('por_defecto')->orderBy('nombre')->pluck('nombre', 'id'))
                            ->helperText(fn () => 'Sugerida por sus datos: ' . (PlanPreventivo::plantillaSugerida($this->getRecord())?->nombre ?? 'ninguna') . '.')
                            ->visible(fn (Get $get) => $get('modo') === 'plantilla'),
                        Repeater::make('rutinas')
                            ->label('Rutinas del vehículo')
                            ->visible(fn (Get $get) => $get('modo') === 'personalizado')
                            ->reorderable()
                            ->addActionLabel('Agregar rutina')
                            ->itemLabel(fn (array $state) => $state['nombre'] ?? null)
                            ->collapsible()
                            ->columns(['default' => 2, 'md' => 5])
                            ->helperText('Quitar una rutina la desactiva para este vehículo; su historial se conserva.')
                            ->schema([
                                TextInput::make('id')->hidden()->dehydrated(),
                                TextInput::make('nombre')->required()->maxLength(255)->columnSpan(['default' => 2, 'md' => 2]),
                                TextInput::make('intervalo_km')->label('Cada (km)')->numeric()->minValue(100),
                                TextInput::make('intervalo_meses')->label('O cada (meses)')->numeric()->minValue(1)->maxValue(120),
                                TextInput::make('descripcion')->label('Detalle')->maxLength(255),
                            ]),
                    ]),
            ]);
    }

    public function partidaForm(Schema $schema): Schema
    {
        $v = $this->vehiculo();
        $kmActual = $v->kilometrajeActual();

        return $schema
            ->statePath('partida')
            ->disabled(! $this->puedeEditar())
            ->components([
                Section::make('Punto de partida')
                    ->description('Cuándo y a cuántos km se hizo cada rutina por última vez. Desde ahí se calcula el próximo y se programa el año. '
                        . 'Kilometraje actual: ' . ($kmActual ? number_format($kmActual, 0, ',', '.') . ' km' : 'sin reporte') . ' · '
                        . PlanPreventivo::kmDiarios($v) . ' km/día.')
                    ->schema(PlanPreventivo::para($v)->map(fn ($f) => Grid::make(['default' => 1, 'md' => 4])->schema([
                        Text::make($f['rutina']->nombre)->weight('bold')->columnSpan(['md' => 1]),
                        Text::make($f['ultimo']
                            ? 'Último: ' . $f['ultimo']->fecha->format('d/m/Y') . ($f['ultimo']->kilometraje !== null ? ' · ' . number_format($f['ultimo']->kilometraje, 0, ',', '.') . ' km' : '') . ($f['ultimo']->registro_inicial ? ' (punto de partida)' : '')
                            : 'Sin registro')->color('gray'),
                        DatePicker::make("puntos.{$f['rutina']->id}.fecha")->hiddenLabel()->placeholder('Fecha')->maxDate(today()),
                        TextInput::make("puntos.{$f['rutina']->id}.kilometraje")->hiddenLabel()->placeholder('Km')->numeric()->minValue(0),
                    ]))->values()->all()),
            ]);
    }

    public function guardarPlan(): void
    {
        abort_unless($this->puedeEditar(), 403);
        $datos = $this->planForm->getState();
        $v = $this->vehiculo();

        try {
            if ($datos['modo'] === 'plantilla') {
                PlanDeVehiculo::seguirPlantilla($v, PlantillaMantenimiento::query()->find($datos['plantilla_mantenimiento_id'] ?? null));
            } else {
                PlanDeVehiculo::personalizar($v, array_values($datos['rutinas'] ?? []));
            }
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ['plan.' . $k => $m])->all());
        }

        Notification::make()->success()->title('Plan guardado')->send();
        $this->llenar();
    }

    public function guardarPartida(): void
    {
        abort_unless($this->puedeEditar(), 403);

        try {
            $n = PlanDeVehiculo::puntoDePartida($this->vehiculo(), $this->partidaForm->getState()['puntos'] ?? [], auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ['partida.' . $k => $m])->all());
        }

        Notification::make()->success()->title($n ? 'Punto de partida registrado' : 'No escribió fechas ni kilómetros')->send();
        $this->llenar();
    }

    /** Rutinas sin registro: toma hoy y el km actual como punto de partida. */
    public function partidaHoy(): void
    {
        abort_unless($this->puedeEditar(), 403);
        $v = $this->vehiculo();
        $km = $v->kilometrajeActual();
        $puntos = PlanPreventivo::para($v)->where('estado', PlanPreventivo::SIN_REGISTRO)
            ->mapWithKeys(fn ($f) => [$f['rutina']->id => ['fecha' => today()->toDateString(), 'kilometraje' => $km]])->all();

        $n = $puntos ? PlanDeVehiculo::puntoDePartida($v, $puntos, auth()->user()) : 0;
        Notification::make()->success()->title($n ? count($puntos) . ' rutina(s) arrancan hoy' : 'Todas las rutinas ya tienen registro')->send();
        $this->llenar();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copiar')
                ->label('Copiar de otro vehículo')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->visible(fn () => $this->puedeEditar())
                ->schema([
                    Select::make('origen')->label('Vehículo')->required()->searchable()
                        ->options(fn () => Vehiculo::query()->enFlota()->whereKeyNot($this->getRecord()->id)->orderBy('placa')->pluck('placa', 'id')),
                ])
                ->modalDescription('El plan de este vehículo queda personalizado con las rutinas e intervalos del otro.')
                ->action(function (array $data, Action $action): void {
                    ErroresDeNegocio::intentar($action, fn () => PlanDeVehiculo::copiarDe($this->vehiculo(), Vehiculo::query()->findOrFail($data['origen'])));
                    Notification::make()->success()->title('Plan copiado')->send();
                    $this->llenar();
                }),
            Action::make('volver')->label('Hoja de vida')->color('gray')
                ->url(fn () => VehiculoResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            VehiculoResource::getUrl('index') => 'Vehículos',
            VehiculoResource::getUrl('view', ['record' => $this->getRecord()]) => $this->getRecord()->placa,
            'Plan de mantenimiento',
        ];
    }
}
