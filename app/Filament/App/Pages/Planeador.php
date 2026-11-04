<?php

namespace App\Filament\App\Pages;

use App\Enums\EstadoServicio;
use App\Filament\App\Resources\Rutas\RutaResource;
use App\Filament\App\Resources\Servicios\ServicioResource;
use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\Servicio;
use App\Models\Vehiculo;
use App\Support\Operacion\Jornada;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Planeador de servicios (PESV, Res. 40595/2022 paso 15): tablero semanal por vehículo o por
 * conductor con los servicios planificados y los extractos expedidos, la conducción del día de cada
 * conductor frente a la política de la empresa, y los avisos (jornada, sin extracto, sin rutograma).
 */
class Planeador extends Page
{
    protected string $view = 'filament.operacion.planeador';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Planeador';

    protected static ?string $title = 'Planeador de servicios';

    protected static ?string $slug = 'planeador';

    public string $semana = '';

    public string $modo = 'vehiculo';

    public bool $soloOcupados = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ver_servicios') ?? false;
    }

    public function mount(): void
    {
        $this->semana = Carbon::parse(request()->query('semana', today()->toDateString()))->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->modo = in_array(request()->query('modo'), ['vehiculo', 'conductor'], true) ? request()->query('modo') : 'vehiculo';
    }

    public function getSubheading(): ?string
    {
        return 'Cada extracto que se expide aparece aquí con su vehículo. Planifique los servicios con su rutograma y revise la jornada de los conductores (PESV, paso 15).';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rutogramas')->label('Rutogramas')->icon('heroicon-o-map')->color('gray')->url(RutaResource::getUrl()),
            Action::make('planificar')->label('Planificar servicio')->icon('heroicon-o-plus')
                ->visible(fn () => auth()->user()->can('create', Servicio::class))
                ->url(ServicioResource::getUrl('create')),
        ];
    }

    public function cambiarSemana(int $delta): void
    {
        $this->semana = Carbon::parse($this->semana)->addWeeks($delta)->toDateString();
    }

    public function hoy(): void
    {
        $this->semana = today()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    protected function getViewData(): array
    {
        $empresa = Filament::getTenant();
        $desde = Carbon::parse($this->semana)->startOfDay();
        $hasta = $desde->copy()->addDays(6);
        $dias = collect(range(0, 6))->map(fn ($i) => $desde->copy()->addDays($i));

        /** @var Collection<int, Servicio> $servicios */
        $servicios = Servicio::query()->vigentes()->entre($desde, $hasta)
            ->with(['vehiculo', 'conductores', 'origen', 'destino', 'ruta', 'fuec'])
            ->orderBy('hora_salida')->get();

        $minutos = Jornada::minutosPorConductor($servicios, $desde, $hasta);
        $maxDia = (int) $empresa->jornada_max_horas_dia * 60;

        $avisos = $servicios->mapWithKeys(fn (Servicio $s) => [$s->id => Jornada::avisos($s, $s->conductores->pluck('id')->all())])->filter();

        if ($this->modo === 'conductor') {
            $filas = Conductor::query()->vinculados()->orderBy('nombres')->get()
                ->map(fn (Conductor $c) => [
                    'titulo' => $c->nombre_completo,
                    'detalle' => $c->documento,
                    'alerta' => ! $c->puedeOperar(),
                    'clave' => 'c' . $c->id,
                    'nuevo' => ['conductor' => $c->id],
                    'servicios' => $servicios->filter(fn (Servicio $s) => $s->conductores->contains('id', $c->id)),
                    'minutos' => $minutos[$c->id] ?? [],
                ]);
            $sinAsignar = $servicios->filter(fn (Servicio $s) => $s->conductores->isEmpty());
        } else {
            $filas = Vehiculo::query()->enFlota()->with(['documentos', 'marca', 'linea'])->orderBy('placa')->get()
                ->map(fn (Vehiculo $v) => [
                    'titulo' => $v->placa,
                    'detalle' => trim(($v->numero_interno ? "Int. {$v->numero_interno} · " : '') . $v->descripcion),
                    'alerta' => ! $v->puedeOperar(),
                    'clave' => 'v' . $v->id,
                    'nuevo' => ['vehiculo' => $v->id],
                    'servicios' => $servicios->where('vehiculo_id', $v->id),
                    'minutos' => null,
                ]);
            $sinAsignar = $servicios->whereNull('vehiculo_id');
        }

        if ($this->soloOcupados) {
            $filas = $filas->filter(fn ($f) => $f['servicios']->isNotEmpty());
        }

        $antelacion = (int) $empresa->planeacion_antelacion_horas;
        $proximos = $servicios->filter(fn (Servicio $s) => ! $s->fuec_id && $s->salida()->between(now(), now()->addHours(48)));

        return [
            'dias' => $dias,
            'filas' => $filas->values(),
            'sinAsignar' => $sinAsignar,
            'avisos' => $avisos,
            'maxDia' => $maxDia,
            'indicadores' => [
                'Servicios' => $servicios->count(),
                'Con extracto' => $servicios->whereNotNull('fuec_id')->count(),
                'Sin vehículo o conductor' => $servicios->filter(fn (Servicio $s) => ! $s->vehiculo_id || $s->conductores->isEmpty())->count(),
                "Planificados con {$antelacion} h o más" => $servicios->count() ? round(100 * $servicios->filter(fn (Servicio $s) => $s->planificadoConAntelacion($antelacion))->count() / $servicios->count()) . ' %' : '—',
                'Plan leído por el conductor' => $servicios->whereNotNull('fuec_id')->count() ? round(100 * $servicios->whereNotNull('fuec_id')->whereNotNull('leido_at')->count() / $servicios->whereNotNull('fuec_id')->count()) . ' %' : '—',
                'Con avisos de jornada' => $avisos->count(),
            ],
            'proximos' => $proximos,
            'sinRuta' => $servicios->whereNull('ruta_id'),
            'urlNuevo' => fn (array $params) => ServicioResource::getUrl('create', $params),
            'urlVer' => fn (Servicio $s) => ServicioResource::getUrl('view', ['record' => $s]),
            'puedeCrear' => auth()->user()->can('create', Servicio::class),
        ];
    }
}
