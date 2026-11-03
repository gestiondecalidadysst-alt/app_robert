<?php

namespace App\Filament\App\Pages;

use App\Actions\Mantenimiento\ProgramaAnual;
use App\Filament\App\Resources\ProgramasMantenimiento\ProgramaMantenimientoResource;
use App\Models\ActividadMantenimiento;
use App\Models\Vehiculo;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Calendario mensual del programa de mantenimiento: qué le toca a cada vehículo y en qué estado
 * (programada, próxima, vencida, ejecutada, cancelada).
 */
class CalendarioMantenimiento extends Page
{
    protected string $view = 'filament.mantenimiento.calendario';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 27;

    protected static ?string $navigationLabel = 'Calendario';

    protected static ?string $title = 'Calendario de mantenimiento';

    protected static ?string $slug = 'calendario-mantenimiento';

    public string $mes = '';

    public ?string $vehiculo = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ver_programas_mantenimiento') ?? false;
    }

    public function mount(): void
    {
        $this->mes = request()->query('mes', today()->format('Y-m'));
    }

    public function cambiarMes(int $delta): void
    {
        $this->mes = Carbon::createFromFormat('Y-m-d', $this->mes . '-01')->addMonthsNoOverflow($delta)->format('Y-m');
    }

    public function hoy(): void
    {
        $this->mes = today()->format('Y-m');
    }

    protected function getViewData(): array
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $this->mes . '-01')->startOfDay();
        $programa = ProgramaAnual::programaVigente(Filament::getTenant()->id, (int) $inicio->year);

        /** @var Collection<string, Collection<int, ActividadMantenimiento>> $porDia */
        $porDia = $programa
            ? $programa->actividades()->with('vehiculo')
                ->when($this->vehiculo, fn ($q) => $q->where('vehiculo_id', $this->vehiculo))
                ->where(fn ($q) => $q->whereBetween('fecha_programada', [$inicio, $inicio->copy()->endOfMonth()])
                    ->orWhereBetween('fecha_ejecucion', [$inicio, $inicio->copy()->endOfMonth()]))
                ->orderBy('vehiculo_id')->get()
                ->groupBy(fn (ActividadMantenimiento $a) => ($a->fecha_ejecucion ?? $a->fecha_programada)->toDateString())
            : collect();

        $semanas = [];
        $dia = $inicio->copy()->startOfWeek(Carbon::MONDAY);
        $fin = $inicio->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        while ($dia->lte($fin)) {
            $semanas[intdiv((int) abs($dia->diffInDays($inicio->copy()->startOfWeek(Carbon::MONDAY))), 7)][] = $dia->copy();
            $dia->addDay();
        }

        return [
            'inicio' => $inicio,
            'semanas' => $semanas,
            'porDia' => $porDia,
            'programa' => $programa,
            'urlPrograma' => $programa ? ProgramaMantenimientoResource::getUrl('view', ['record' => $programa]) : ProgramaMantenimientoResource::getUrl('index'),
            'vehiculos' => Vehiculo::query()->enFlota()->orderBy('placa')->pluck('placa', 'id'),
        ];
    }
}
