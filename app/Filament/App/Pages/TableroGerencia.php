<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Gerencia\Widgets\CarteraPorEdades;
use App\Filament\App\Gerencia\Widgets\ResumenCartera;
use App\Filament\App\Gerencia\Widgets\ResumenFlota;
use App\Filament\App\Gerencia\Widgets\ResumenMantenimiento;
use App\Filament\App\Gerencia\Widgets\ResumenOperacion;
use App\Filament\App\Gerencia\Widgets\ResumenSeguridadVial;
use App\Filament\App\Gerencia\Widgets\TablasGerencia;
use App\Filament\App\Gerencia\Widgets\TendenciaFinanciera;
use App\Filament\App\Gerencia\Widgets\TendenciaOperativa;
use App\Models\Oficina;
use App\Support\Gerencia\InformeGerencia;
use App\Support\Gerencia\Indicadores;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Tablero de gerencia (Módulo de gerencia): flota, operación, cartera de contratos, seguridad vial,
 * mantenimiento y tendencias de 12 meses, con filtro de periodo y oficina. Cada número lleva al
 * listado de donde sale. Los cálculos están en App\Support\Gerencia\Indicadores.
 */
class TableroGerencia extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/gerencia';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string|UnitEnum|null $navigationGroup = 'Gerencia';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Tablero';

    protected static ?string $title = 'Tablero de gerencia';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ver_gerencia') ?? false;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 4])->schema([
                Select::make('periodo')->label('Periodo')->default('mes')->selectablePlaceholder(false)
                    ->options([
                        'hoy' => 'Hoy',
                        'semana' => 'Esta semana',
                        'mes' => 'Este mes',
                        'mes_anterior' => 'Mes anterior',
                        'anio' => 'Este año',
                        'rango' => 'Otro rango',
                    ]),
                DatePicker::make('desde')->visible(fn (Get $get) => $get('periodo') === 'rango'),
                DatePicker::make('hasta')->visible(fn (Get $get) => $get('periodo') === 'rango'),
                Select::make('oficina_id')->label('Oficina')->placeholder('Todas')
                    ->options(fn () => Oficina::query()->orderBy('nombre')->pluck('nombre', 'id'))
                    ->helperText('Filtra contratos, pagos y extractos.'),
            ]),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            ResumenFlota::class,
            ResumenOperacion::class,
            ResumenCartera::class,
            ResumenSeguridadVial::class,
            ResumenMantenimiento::class,
            TendenciaFinanciera::class,
            TendenciaOperativa::class,
            CarteraPorEdades::class,
            TablasGerencia::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['md' => 2];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('informes')->label('Informes')->icon('heroicon-o-table-cells')->color('gray')->url(Informes::getUrl()),
            Action::make('pdf')->label('Resumen en PDF')->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $f = $this->filters ?? [];
                    [$desde, $hasta] = Indicadores::periodo($f['periodo'] ?? 'mes', $f['desde'] ?? null, $f['hasta'] ?? null);
                    $i = new Indicadores(Filament::getTenant()->id, $desde, $hasta, filled($f['oficina_id'] ?? null) ? (int) $f['oficina_id'] : null);

                    return response()->streamDownload(fn () => print (InformeGerencia::pdf($i)), InformeGerencia::nombre($i), ['Content-Type' => 'application/pdf']);
                }),
        ];
    }
}
