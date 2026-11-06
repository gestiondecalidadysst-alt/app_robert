<?php

namespace App\Filament\App\Pages;

use App\Support\Gerencia\ExportarInforme;
use App\Support\Pdf\PdfTablaInforme;
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
 * Informes del módulo de operación (servicios por día y extractos expedidos), en Excel o PDF.
 *
 * Reutiliza el mismo generador que los informes de gerencia, así que el PDF sale con el
 * aspecto estándar del sistema (encabezado con logo y empresa, pie con fecha y número de
 * página). Se separa de Gerencia para que quien gestiona la operación lo tenga a mano sin
 * pasar por el permiso de gerencia.
 */
class InformesOperacion extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.operacion.informes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Informes de operación';

    protected static ?string $slug = 'informes-operacion';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Catálogo acotado a lo que es de operación. */
    public static function informes(): array
    {
        return array_intersect_key(ExportarInforme::INFORMES, array_flip(ExportarInforme::DE_OPERACION));
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ver_servicios') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'informe' => 'operacion',
            'formato' => 'excel',
            'desde' => today()->startOfMonth()->toDateString(),
            'hasta' => today()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make()->columns(['default' => 1, 'md' => 4])->schema([
                Select::make('informe')->label('Informe')->options(self::informes())
                    ->required()->selectablePlaceholder(false)->columnSpan(['md' => 2]),
                Select::make('formato')->label('Formato')
                    ->options(['excel' => 'Excel', 'pdf' => 'PDF'])->default('excel')->selectablePlaceholder(false),
                DatePicker::make('desde')->required(),
                DatePicker::make('hasta')->required()->afterOrEqual('desde'),
                Actions::make([
                    Action::make('descargar')->label('Descargar')->icon('heroicon-o-arrow-down-tray')->action(fn () => $this->descargar()),
                ])->columnSpan(['md' => 4])->verticalAlignment('end'),
            ]),
        ]);
    }

    public function descargar()
    {
        $d = $this->form->getState();
        abort_unless(in_array($d['informe'], ExportarInforme::DE_OPERACION, true), 404);

        $desde = Carbon::parse($d['desde']);
        $hasta = Carbon::parse($d['hasta']);
        $empresa = Filament::getTenant()->id;

        if (($d['formato'] ?? 'excel') === 'pdf') {
            return response()->streamDownload(
                fn () => print (ExportarInforme::pdf($d['informe'], $empresa, $desde, $hasta)),
                PdfTablaInforme::nombre($d['informe'], $desde, $hasta),
                ['Content-Type' => 'application/pdf'],
            );
        }

        return response()->download(
            ExportarInforme::excel($d['informe'], $empresa, $desde, $hasta),
            ExportarInforme::nombre($d['informe'], $desde, $hasta),
        )->deleteFileAfterSend();
    }
}
