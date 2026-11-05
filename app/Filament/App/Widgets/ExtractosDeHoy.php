<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\Fuecs\FuecResource;
use App\Models\Fuec;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Inicio: extractos vigentes hoy con el estado del preoperacional del vehículo. */
class ExtractosDeHoy extends TableWidget
{
    protected static ?int $sort = -18;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('ver_fuecs') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Extractos vigentes hoy')
            ->query(fn () => Fuec::query()->noAnulados()->whereDate('fecha_inicio', '<=', today())->whereDate('fecha_fin', '>=', today())
                ->with(['vehiculo', 'conductores', 'origen', 'destino', 'contrato.cliente'])->orderBy('hora_inicio'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Sin extractos vigentes hoy')
            ->recordUrl(fn (Fuec $record) => FuecResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('numero')->label('FUEC')->formatStateUsing(fn (Fuec $record) => $record->numero_formateado)->size('sm')->visibleFrom('lg'),
                TextColumn::make('placa')->label('Vehículo')->state(fn (Fuec $record) => $record->placa)->badge()->color('primary'),
                TextColumn::make('conductores')->label('Conductor')->state(fn (Fuec $record) => $record->conductores->map->nombre_completo->implode(', '))->limit(30),
                TextColumn::make('ruta')->label('Ruta')->state(fn (Fuec $record) => ($record->origen?->nombre ?? '').' → '.($record->destino?->nombre ?? ''))
                    ->description(fn (Fuec $record) => $record->horario()->franjaTexto()),
                TextColumn::make('cliente')->label('Cliente')->state(fn (Fuec $record) => $record->contrato?->cliente?->nombre)->limit(28)->visibleFrom('md'),
                TextColumn::make('preop')->label('Preoperacional')->badge()
                    ->state(function (Fuec $record) {
                        $p = $record->vehiculo?->preoperacionalDelDia();

                        return ! $p ? 'Pendiente' : ($p->apta() ? 'Apto' : 'No apto');
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Apto' => 'success',
                        'No apto' => 'danger',
                        default => 'warning',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'Apto' => 'heroicon-m-check-circle',
                        'No apto' => 'heroicon-m-x-circle',
                        default => 'heroicon-m-clock',
                    }),
            ]);
    }
}
