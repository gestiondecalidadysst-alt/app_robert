<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\Contratos\ContratoResource;
use App\Models\Contrato;
use App\Support\Gerencia\Indicadores;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Inicio: contratos con saldo, del más vencido al que vence después. */
class PendientesPorCobrar extends TableWidget
{
    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('ver_contratos') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pendientes por cobrar')
            ->description('Contratos con saldo, del más vencido al que vence después (fecha límite de pago o fin del contrato).')
            ->query(fn () => Contrato::query()->noAnulados()->with('cliente')
                ->withSum(['pagos' => fn ($q) => $q->whereNull('anulado_at')], 'valor')
                ->whereRaw('contratos.valor > (select coalesce(sum(valor), 0) from pagos_contrato where pagos_contrato.contrato_id = contratos.id and pagos_contrato.anulado_at is null)')
                ->orderByRaw('coalesce(fecha_limite_pago, fecha_fin)'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Todo cobrado')
            ->emptyStateDescription('No hay contratos con saldo pendiente.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->recordUrl(fn (Contrato $record) => ContratoResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('numero')->label('Contrato')->weight('bold')
                    ->formatStateUsing(fn (Contrato $record) => $record->numero_formateado),
                TextColumn::make('cliente.nombre')->label('Cliente')->limit(32)->searchable(),
                TextColumn::make('valor')->formatStateUsing(fn ($state) => Indicadores::pesos($state))->alignEnd()->visibleFrom('md'),
                TextColumn::make('pagado')->label('Abonado')->state(fn (Contrato $record) => Indicadores::pesos($record->totalPagado()))->alignEnd()->visibleFrom('md')->color('success'),
                TextColumn::make('saldo')->label('Saldo')->state(fn (Contrato $record) => Indicadores::pesos($record->saldo()))->alignEnd()->weight('bold')->color('danger'),
                TextColumn::make('vence')->label('Vencimiento')->badge()
                    ->state(function (Contrato $record) {
                        $vence = $record->fecha_limite_pago ?? $record->fecha_fin;
                        $dias = (int) today()->diffInDays($vence, false);

                        return match (true) {
                            $dias < 0 => 'Vencido hace '.abs($dias).' día'.(abs($dias) === 1 ? '' : 's'),
                            $dias === 0 => 'Vence hoy',
                            default => "Vence en {$dias} día".($dias === 1 ? '' : 's'),
                        };
                    })
                    ->color(function (Contrato $record) {
                        $dias = (int) today()->diffInDays($record->fecha_limite_pago ?? $record->fecha_fin, false);

                        return $dias < 0 ? 'danger' : ($dias <= 7 ? 'warning' : 'gray');
                    })
                    ->icon(fn (string $state) => str_starts_with($state, 'Vencido') ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-clock'),
            ])
            ->recordActions([
                Action::make('cobrar')->iconButton()->label('Registrar pago')->icon('heroicon-o-banknotes')->button()->size('sm')
                    ->visible(fn () => auth()->user()->can('crear_pagos_contrato'))
                    ->url(fn (Contrato $record) => ContratoResource::getUrl('view', ['record' => $record, 'relation' => 'pagos'])),
            ]);
    }
}
