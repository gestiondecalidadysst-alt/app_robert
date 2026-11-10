<?php

namespace App\Filament\Admin\Pages;

use App\Models\Empresa;
use App\Models\EnvioMensaje;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use UnitEnum;

/**
 * Consumo de SMS por empresa y mes, para facturar: mensajes enviados, segmentos, lo que se le
 * cobra a cada empresa, lo que le cuesta a la plataforma y el margen. Solo los enviados se cobran.
 */
class ConsumoSms extends Page
{
    protected string $view = 'filament.admin.consumo-sms';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 26;

    protected static ?string $navigationLabel = 'Consumo de SMS';

    public function getSubheading(): ?string
    {
        return 'Mensajes enviados por cada empresa en el mes y el valor a cobrarle. Los precios generales se definen en «Mensajes de texto».';
    }

    protected static ?string $title = 'Consumo de SMS';

    protected static ?string $slug = 'consumo-sms';

    public string $mes = '';

    public function mount(): void
    {
        $this->mes = request()->query('mes', now()->format('Y-m'));
    }

    private function inicioMes(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->mes.'-01')->startOfMonth();
    }

    /** @return Collection<int, array<string, mixed>> una fila por empresa con SMS enviados en el mes */
    public function resumen(): Collection
    {
        $inicio = $this->inicioMes();

        $filas = EnvioMensaje::query()->withoutGlobalScopes()
            ->where('canal', 'sms')->where('estado', 'enviado')
            ->whereBetween('created_at', [$inicio, $inicio->copy()->endOfMonth()])
            ->selectRaw('empresa_id, count(*) as mensajes, sum(segmentos) as segmentos, sum(precio) as precio, sum(costo) as costo')
            ->groupBy('empresa_id')->get();

        $empresas = Empresa::query()->withoutGlobalScopes()->whereKey($filas->pluck('empresa_id'))->pluck('razon_social', 'id');

        return $filas->map(fn ($f) => [
            'empresa' => $empresas[$f->empresa_id] ?? "#{$f->empresa_id}",
            'mensajes' => (int) $f->mensajes,
            'segmentos' => (int) $f->segmentos,
            'cobrar' => (int) $f->precio,
            'costo' => (int) $f->costo,
        ])->sortBy('empresa')->values();
    }

    protected function getViewData(): array
    {
        $resumen = $this->resumen();

        return [
            'resumen' => $resumen,
            'totales' => [
                'mensajes' => $resumen->sum('mensajes'),
                'segmentos' => $resumen->sum('segmentos'),
                'cobrar' => $resumen->sum('cobrar'),
                'costo' => $resumen->sum('costo'),
            ],
            'meses' => collect(range(0, 11))->mapWithKeys(fn ($i) => [
                now()->startOfMonth()->subMonths($i)->format('Y-m') => ucfirst(now()->startOfMonth()->subMonths($i)->translatedFormat('F Y')),
            ])->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')->label('Descargar Excel del mes')->icon('heroicon-o-arrow-down-tray')->action(fn () => $this->excel()),
            Action::make('cuenta')->label('Cuenta y precios')->icon('heroicon-o-cog-6-tooth')->color('gray')->url(ProveedorSmsPagina::getUrl()),
        ];
    }

    public function excel()
    {
        $inicio = $this->inicioMes();
        $ruta = tempnam(sys_get_temp_dir(), 'sms').'.xlsx';
        $w = new Writer;
        $w->openToFile($ruta);

        $w->getCurrentSheet()->setName('Resumen por empresa');
        $w->addRow(Row::fromValues(['Consumo de SMS · '.$this->mes]));
        $w->addRow(Row::fromValues(['Empresa', 'Mensajes', 'Segmentos', 'Valor a cobrar', 'Costo plataforma', 'Margen']));
        foreach ($this->resumen() as $r) {
            $w->addRow(Row::fromValues([$r['empresa'], $r['mensajes'], $r['segmentos'], $r['cobrar'], $r['costo'], $r['cobrar'] - $r['costo']]));
        }

        $w->addNewSheetAndMakeItCurrent()->setName('Detalle');
        $w->addRow(Row::fromValues(['Fecha', 'Empresa', 'Tipo', 'Destino', 'Estado', 'Segmentos', 'Precio', 'Costo', 'Error']));
        EnvioMensaje::query()->withoutGlobalScopes()->where('canal', 'sms')
            ->whereBetween('created_at', [$inicio, $inicio->copy()->endOfMonth()])
            ->with('empresa')->orderBy('created_at')->get()
            ->each(fn (EnvioMensaje $e) => $w->addRow(Row::fromValues([
                $e->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i'), $e->empresa?->razon_social,
                $e->tipo, $e->destino, $e->estado, $e->segmentos, $e->precio, $e->costo, $e->error,
            ])));

        $w->close();

        return response()->download($ruta, "consumo-sms-{$this->mes}.xlsx")->deleteFileAfterSend();
    }
}
