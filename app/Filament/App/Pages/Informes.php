<?php

namespace App\Filament\App\Pages;

use App\Models\Oficina;
use App\Support\Gerencia\ExportarInforme;
use App\Support\Gerencia\Indicadores;
use App\Support\Gerencia\InformeGerencia;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Informes de gerencia: Excel de pagos, estado de contratos, extractos expedidos, operación de la
 * flota, preoperacionales y mantenimientos, y el resumen de gerencia en PDF, por rango y oficina.
 */
class Informes extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.gerencia.informes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string|UnitEnum|null $navigationGroup = 'Gerencia';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Informes';

    protected static ?string $slug = 'informes';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ver_gerencia') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'informe' => 'pagos',
            'desde' => today()->startOfMonth()->toDateString(),
            'hasta' => today()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make()->columns(['default' => 1, 'md' => 4])->schema([
                Select::make('informe')->label('Informe')->options(ExportarInforme::INFORMES + ['resumen' => 'Resumen de gerencia (PDF)'])
                    ->required()->selectablePlaceholder(false)->columnSpan(['md' => 2]),
                DatePicker::make('desde')->required(),
                DatePicker::make('hasta')->required()->afterOrEqual('desde'),
                Select::make('oficina_id')->label('Oficina')->placeholder('Todas')
                    ->options(fn () => Oficina::query()->orderBy('nombre')->pluck('nombre', 'id'))
                    ->helperText('Aplica a pagos, contratos, extractos y al resumen.'),
                Actions::make([
                    Action::make('descargar')->label('Descargar')->icon('heroicon-o-arrow-down-tray')->action(fn () => $this->descargar()),
                ])->columnSpan(['md' => 3])->verticalAlignment('end'),
            ]),
        ]);
    }

    public function descargar()
    {
        $d = $this->form->getState();
        $desde = Carbon::parse($d['desde']);
        $hasta = Carbon::parse($d['hasta']);
        $empresa = Filament::getTenant()->id;
        $oficina = filled($d['oficina_id'] ?? null) ? (int) $d['oficina_id'] : null;

        if ($d['informe'] === 'resumen') {
            $i = new Indicadores($empresa, $desde, $hasta, $oficina);

            return response()->streamDownload(fn () => print (InformeGerencia::pdf($i)), InformeGerencia::nombre($i), ['Content-Type' => 'application/pdf']);
        }

        return response()->download(ExportarInforme::excel($d['informe'], $empresa, $desde, $hasta, $oficina), ExportarInforme::nombre($d['informe'], $desde, $hasta))
            ->deleteFileAfterSend();
    }
}
