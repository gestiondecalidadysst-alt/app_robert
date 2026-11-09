<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\Contratos\ContratoResource;
use App\Models\PagoContrato;
use App\Support\Gerencia\Indicadores;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Inicio: últimos pagos recibidos, marcando si fue abono, abono final o pago total. */
class UltimosPagos extends TableWidget
{
    protected static ?int $sort = -19;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('ver_pagos_contrato') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Últimos pagos recibidos')
            ->query(fn () => PagoContrato::query()->with(['contrato.cliente'])->latest('fecha')->latest('id'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Sin pagos todavía')
            ->recordUrl(fn (PagoContrato $record) => ContratoResource::getUrl('view', ['record' => $record->contrato_id]))
            ->columns([
                TextColumn::make('numero')->label('Recibo')->weight('bold')->formatStateUsing(fn (PagoContrato $record) => $record->numero_recibo),
                TextColumn::make('fecha')->date('d/m/Y'),
                TextColumn::make('contrato.cliente.nombre')->label('Cliente')->limit(30)
                    ->description(fn (PagoContrato $record) => 'Contrato '.$record->contrato?->numero_formateado),
                TextColumn::make('tipo')->label('Tipo')->badge()
                    ->state(fn (PagoContrato $record) => $record->anulado() ? 'Anulado' : $record->tipoEtiqueta())
                    ->color(fn (string $state) => match ($state) {
                        'Pago total' => 'success',
                        'Abono final' => 'info',
                        'Anulado' => 'danger',
                        default => 'warning',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'Pago total', 'Abono final' => 'heroicon-m-check-badge',
                        'Anulado' => 'heroicon-m-x-circle',
                        default => 'heroicon-m-arrow-trending-up',
                    }),
                TextColumn::make('valor')->formatStateUsing(fn ($state) => Indicadores::pesos($state))->alignEnd()->weight('bold')->color('success'),
                TextColumn::make('saldo_despues')->label('Saldo después')->alignEnd()->visibleFrom('md')
                    ->formatStateUsing(fn ($state, PagoContrato $record) => $record->anulado() ? '—' : Indicadores::pesos($state))
                    ->color(fn (PagoContrato $record) => (float) $record->saldo_despues > 0 ? 'danger' : 'success'),
                TextColumn::make('metodo')->label('Método')->badge()->color('gray')->visibleFrom('lg'),
            ])
            ->recordActions([
                Action::make('recibo')->iconButton()->label('Recibo')->icon('heroicon-o-arrow-down-tray')->color('gray')
                    ->url(fn (PagoContrato $record) => $record->urlPdf(), shouldOpenInNewTab: true),
            ]);
    }
}
