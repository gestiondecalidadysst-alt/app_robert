<?php

namespace App\Filament\App\Resources\Contratos\Schemas;

use App\Models\Contrato;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ContratoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $pesos = fn ($state) => '$ '.number_format((float) $state, 0, ',', '.');

        return $schema->components([
            Callout::make('Contrato anulado')
                ->danger()
                ->columnSpanFull()
                ->description(fn (Contrato $record) => $record->anulado_at?->timezone('America/Bogota')->format('d/m/Y').': '.$record->motivo_anulacion)
                ->visible(fn (Contrato $record) => $record->anulado()),

            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('cliente.nombre')->label('Contratante')->weight('bold')
                        ->helperText(fn (Contrato $record) => $record->cliente?->identificacion)->columnSpan(2),
                    TextEntry::make('estado')->badge()->state(fn (Contrato $record) => $record->estado()),
                    TextEntry::make('vigencia')->label('Vigencia'),
                    TextEntry::make('objeto')->label('Objeto')
                        ->helperText(fn (Contrato $record) => $record->descripcion_objeto)->columnSpan(2),
                    TextEntry::make('origen.nombre_completo')->label('Origen')->placeholder('—'),
                    TextEntry::make('destino.nombre_completo')->label('Destino')->placeholder('—'),
                    TextEntry::make('recorrido')->label('Recorrido')->placeholder('—')->columnSpan(2),
                    TextEntry::make('disposicion_total')->label('Disposición total')
                        ->formatStateUsing(fn (bool $state, Contrato $record) => $state ? 'Sí · '.$record->zonas_disposicion : 'No'),
                    TextEntry::make('cantidad_pasajeros')->label('Pasajeros / vehículos')
                        ->state(fn (Contrato $record) => ($record->cantidad_pasajeros ?? '—').' / '.($record->cantidad_vehiculos ?? '—')),
                ]),

            Section::make('Valor')
                ->icon('heroicon-o-banknotes')
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('valor')->label('Valor del contrato')->formatStateUsing($pesos)->weight('bold'),
                    TextEntry::make('pagado')->label('Pagado')->state(fn (Contrato $record) => $pesos($record->totalPagado())),
                    TextEntry::make('saldo')->label('Saldo')->state(fn (Contrato $record) => $pesos($record->saldo()))
                        ->color(fn (Contrato $record) => $record->saldo() > 0 ? 'danger' : 'success')->weight('bold'),
                    TextEntry::make('forma_pago')->label('Forma de pago')
                        ->helperText(fn (Contrato $record) => $record->fecha_limite_pago ? 'Pagar antes del '.$record->fecha_limite_pago->format('d/m/Y') : null),
                ]),

            Section::make('Responsable')
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 4])
                ->collapsed()
                ->schema([
                    TextEntry::make('responsable_nombre')->label('Nombre')->placeholder('—'),
                    TextEntry::make('responsable_documento')->label('Documento')->placeholder('—'),
                    TextEntry::make('responsable_telefono')->label('Teléfono')->placeholder('—'),
                    TextEntry::make('responsable_direccion')->label('Dirección')->placeholder('—'),
                    TextEntry::make('usuario.name')->label('Registró')->placeholder('—'),
                    TextEntry::make('oficina.nombre')->label('Oficina')->placeholder('—'),
                    TextEntry::make('observaciones')->placeholder('—')->columnSpan(2),
                ]),
        ]);
    }
}
