<?php

namespace App\Filament\Admin\Pages;

use App\Enums\TipoConsultaTransito;
use App\Models\ConsultaTransito;
use App\Models\Empresa;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use UnitEnum;

/**
 * Consumo de consultas de tránsito por empresa y mes, para facturar: consultas por tipo, lo que se
 * le cobra a cada empresa, lo que cuesta al proveedor y el margen. Detalle de cada consulta y Excel.
 */
class ConsumoConsultas extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.admin.consumo-consultas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Consumo de consultas';

    protected static ?string $title = 'Consumo de consultas de tránsito';

    protected static ?string $slug = 'consumo-consultas';

    public string $mes = '';

    public function mount(): void
    {
        $this->mes = request()->query('mes', now()->format('Y-m'));
    }

    public function updatedMes(): void
    {
        $this->resetTable();
    }

    private function inicioMes(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->mes . '-01')->startOfMonth();
    }

    /** @return Collection<int, array> una fila por empresa con consultas en el mes */
    public function resumen(): Collection
    {
        $filas = ConsultaTransito::query()->withoutGlobalScopes()->delMes($this->inicioMes())
            ->selectRaw('empresa_id, tipo, count(*) as total, sum(case when cobrada = 1 then 1 else 0 end) as cobradas, sum(precio) as precio, sum(costo) as costo')
            ->groupBy('empresa_id', 'tipo')->get()->groupBy('empresa_id');
        $empresas = Empresa::query()->whereKey($filas->keys())->pluck('razon_social', 'id');

        return $filas->map(fn (Collection $tipos, $empresaId) => [
            'empresa' => $empresas[$empresaId] ?? "#{$empresaId}",
            'por_tipo' => collect(TipoConsultaTransito::cases())->mapWithKeys(fn ($t) => [$t->value => (int) ($tipos->firstWhere('tipo', $t)?->cobradas ?? 0)])->all(),
            'consultas' => (int) $tipos->sum('total'),
            'cobradas' => (int) $tipos->sum('cobradas'),
            'cobrar' => (float) $tipos->sum('precio'),
            'costo' => (float) $tipos->sum('costo'),
        ])->sortBy('empresa')->values();
    }

    protected function getViewData(): array
    {
        $resumen = $this->resumen();

        return [
            'resumen' => $resumen,
            'totales' => ['cobrar' => $resumen->sum('cobrar'), 'costo' => $resumen->sum('costo'), 'cobradas' => $resumen->sum('cobradas'), 'consultas' => $resumen->sum('consultas')],
            'meses' => collect(range(0, 11))->mapWithKeys(fn ($i) => [now()->startOfMonth()->subMonths($i)->format('Y-m') => ucfirst(now()->startOfMonth()->subMonths($i)->translatedFormat('F Y'))])->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')->label('Descargar Excel del mes')->icon('heroicon-o-arrow-down-tray')->action(fn () => $this->excel()),
            Action::make('precios')->label('Proveedor y precios')->icon('heroicon-o-cog-6-tooth')->color('gray')->url(ProveedorConsultas::getUrl()),
        ];
    }

    public function excel()
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cons') . '.xlsx';
        $w = new Writer;
        $w->openToFile($ruta);
        $w->getCurrentSheet()->setName('Resumen por empresa');
        $w->addRow(Row::fromValues(['Consumo de consultas de tránsito · ' . $this->mes]));
        $w->addRow(Row::fromValues(['Empresa', ...array_map(fn ($t) => $t->getLabel(), TipoConsultaTransito::cases()), 'Consultas', 'Cobradas', 'Valor a cobrar', 'Costo proveedor', 'Margen']));
        foreach ($this->resumen() as $r) {
            $w->addRow(Row::fromValues([$r['empresa'], ...array_values($r['por_tipo']), $r['consultas'], $r['cobradas'], $r['cobrar'], $r['costo'], $r['cobrar'] - $r['costo']]));
        }
        $w->addNewSheetAndMakeItCurrent()->setName('Detalle');
        $w->addRow(Row::fromValues(['Fecha', 'Empresa', 'Tipo', 'Consultado', 'Origen', 'Usuario', 'Resultado', 'Cobrada', 'Créditos', 'Precio', 'Costo', 'Error']));
        ConsultaTransito::query()->withoutGlobalScopes()->delMes($this->inicioMes())->with(['empresa', 'usuario'])->orderBy('created_at')->get()
            ->each(fn (ConsultaTransito $c) => $w->addRow(Row::fromValues([
                $c->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i'), $c->empresa?->razon_social, $c->tipo->getLabel(), $c->referencia,
                $c->origen, $c->usuario?->name, $c->etiquetaEstado(), $c->cobrada ? 'Sí' : 'No', $c->creditos, (float) $c->precio, (float) $c->costo, $c->error,
            ])));
        $w->close();

        return response()->download($ruta, "consumo-consultas-{$this->mes}.xlsx")->deleteFileAfterSend();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ConsultaTransito::query()->withoutGlobalScopes()->delMes($this->inicioMes())->with(['empresa', 'usuario']))
            ->heading('Detalle de consultas')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i a')->timezone(config('app.timezone')),
                TextColumn::make('empresa.razon_social')->label('Empresa')->limit(28),
                TextColumn::make('tipo')->badge()->formatStateUsing(fn (TipoConsultaTransito $state) => $state->getLabel()),
                TextColumn::make('referencia')->label('Consultado'),
                TextColumn::make('origen')->badge()->color('gray'),
                TextColumn::make('estado')->label('Resultado')->badge()
                    ->formatStateUsing(fn (string $state, ConsultaTransito $record) => $record->etiquetaEstado())
                    ->color(fn (string $state) => match ($state) { 'ok' => 'success', 'sin_datos' => 'gray', default => 'danger' }),
                IconColumn::make('cobrada')->boolean(),
                TextColumn::make('precio')->label('Cobro')->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))->alignEnd(),
                TextColumn::make('costo')->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))->alignEnd()->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('empresa_id')->label('Empresa')->options(fn () => Empresa::query()->orderBy('razon_social')->pluck('razon_social', 'id')),
                SelectFilter::make('tipo')->options(TipoConsultaTransito::class),
            ]);
    }
}
