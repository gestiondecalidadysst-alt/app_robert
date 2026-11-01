<?php

namespace App\Filament\App\Resources\Inspecciones\Pages;

use App\Enums\TipoInspeccion;
use App\Filament\App\Resources\Inspecciones\InspeccionResource;
use App\Models\Inspeccion;
use App\Models\Vehiculo;
use App\Support\Nit;
use App\Support\Pdf\PdfInspeccion;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ListarInspecciones extends ListRecords
{
    protected static string $resource = InspeccionResource::class;

    protected static ?string $title = 'Preoperacionales e inspecciones';

    public function getSubheading(): ?string
    {
        $hoy = Inspeccion::query()->preoperacionales()->delDia()->get();
        $vehiculos = $hoy->pluck('vehiculo_id')->unique()->count();
        $noAptos = $hoy->groupBy('vehiculo_id')->filter(fn ($i) => ! $i->sortByDesc('id')->first()->apta())->count();

        return "Hoy: {$vehiculos} vehículos con preoperacional · {$noAptos} no aptos · " . PendientesHoy::cantidad() . ' sin preoperacional';
    }

    public function getTabs(): array
    {
        return [
            'todas' => Tab::make('Todas'),
            ...collect(TipoInspeccion::cases())->mapWithKeys(fn (TipoInspeccion $t) => [
                $t->value => Tab::make($t === TipoInspeccion::Preoperacional ? 'Preoperacionales' : 'Mensuales')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('tipo', $t->value)),
            ])->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pendientes')
                ->label('Sin preoperacional hoy')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->badge(fn () => PendientesHoy::cantidad() ?: null)
                ->url(InspeccionResource::getUrl('pendientes')),
            Action::make('informe')
                ->label('Informe PDF')
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->modalHeading('Informe del preoperacional por vehículo')
                ->modalDescription('Matriz de ítems por día (máximo 31 días), con kilometraje, conductor, resultado y novedades.')
                ->schema([
                    Select::make('vehiculo_id')->label('Vehículo')->required()->searchable()
                        ->options(fn () => Vehiculo::query()->enFlota()->orderBy('placa')->pluck('placa', 'id')),
                    DatePicker::make('desde')->required()->default(today()->startOfMonth()),
                    DatePicker::make('hasta')->required()->default(today())->afterOrEqual('desde'),
                ])
                ->modalSubmitActionLabel('Generar PDF')
                ->action(function (array $data) {
                    $desde = Carbon::parse($data['desde'])->startOfDay();
                    $hasta = Carbon::parse($data['hasta'])->startOfDay();
                    if ($desde->diffInDays($hasta) > 30) {
                        throw ValidationException::withMessages(['mountedActionSchema0.hasta' => 'El informe cubre máximo 31 días.']);
                    }

                    $vehiculo = Vehiculo::query()->findOrFail($data['vehiculo_id']);
                    $empresa = Filament::getTenant();
                    $inspecciones = Inspeccion::query()->preoperacionales()->where('vehiculo_id', $vehiculo->id)
                        ->whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta)->get();

                    $pdf = PdfInspeccion::informe($vehiculo, $desde, $hasta, $inspecciones, [
                        'razon_social' => $empresa->razon_social,
                        'nit' => Nit::formatear($empresa->nit, $empresa->dv),
                        'logo_path' => $empresa->logo_path,
                    ]);

                    return response()->streamDownload(fn () => print ($pdf),
                        "Informe-preoperacional-{$vehiculo->placa}-{$desde->format('Ymd')}-{$hasta->format('Ymd')}.pdf",
                        ['Content-Type' => 'application/pdf']);
                }),
            Action::make('mensual')
                ->label('Registrar inspección mensual')
                ->icon('heroicon-o-clipboard-document-list')
                ->visible(fn () => auth()->user()?->can('crear_inspecciones'))
                ->url(InspeccionResource::getUrl('mensual')),
        ];
    }
}
