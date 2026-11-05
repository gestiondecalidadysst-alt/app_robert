<?php

namespace App\Filament\App\Resources\Mantenimientos\Pages;

use App\Filament\App\Resources\Mantenimientos\MantenimientoResource;
use App\Models\Mantenimiento;
use App\Models\Vehiculo;
use App\Support\Nit;
use App\Support\Pdf\PdfMantenimientos;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListarMantenimientos extends ListRecords
{
    protected static ?string $title = 'Mantenimientos';

    protected static string $resource = MantenimientoResource::class;

    public function getSubheading(): ?string
    {
        $mes = Mantenimiento::query()->reales()->whereDate('fecha', '>=', today()->startOfMonth());

        return 'Este mes: '.(clone $mes)->count().' mantenimientos · $ '.number_format((float) (clone $mes)->sum('valor'), 0, ',', '.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('informe')
                ->label('Informe PDF')
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->schema([
                    Select::make('vehiculo_id')->label('Vehículo')->placeholder('Todos')->searchable()
                        ->options(fn () => Vehiculo::query()->orderBy('placa')->pluck('placa', 'id')),
                    DatePicker::make('desde')->required()->default(today()->startOfYear()),
                    DatePicker::make('hasta')->required()->default(today())->afterOrEqual('desde'),
                ])
                ->modalSubmitActionLabel('Generar PDF')
                ->action(function (array $data) {
                    $empresa = Filament::getTenant();
                    $desde = Carbon::parse($data['desde']);
                    $hasta = Carbon::parse($data['hasta']);
                    $vehiculo = filled($data['vehiculo_id'] ?? null) ? Vehiculo::query()->find($data['vehiculo_id']) : null;
                    $registros = Mantenimiento::query()->reales()->with(['vehiculo', 'rutinas'])->withCount('evidencias')
                        ->when($vehiculo, fn ($q) => $q->where('vehiculo_id', $vehiculo->id))
                        ->whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta)
                        ->orderBy('fecha')->orderBy('id')->get();

                    $pdf = PdfMantenimientos::informe($registros, $desde, $hasta, $vehiculo, [
                        'razon_social' => $empresa->razon_social,
                        'nit' => Nit::formatear($empresa->nit, $empresa->dv),
                        'logo_path' => $empresa->logo_path,
                    ]);

                    return response()->streamDownload(fn () => print ($pdf),
                        'Mantenimientos-'.($vehiculo?->placa ?? 'flota')."-{$desde->format('Ymd')}-{$hasta->format('Ymd')}.pdf",
                        ['Content-Type' => 'application/pdf']);
                }),
            CreateAction::make()->label('Registrar mantenimiento')->icon('heroicon-o-plus'),
        ];
    }
}
