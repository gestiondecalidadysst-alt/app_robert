<?php

namespace App\Filament\App\Resources\Fuecs\Schemas;

use App\Filament\App\Resources\Contratos\ContratoResource;
use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Models\Fuec;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Detalle del FUEC, tomado de la copia de datos guardada al expedirlo.
 */
final class FuecInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $fecha = fn (?string $f) => $f ? Carbon::parse($f)->format('d/m/Y') : '—';

        return $schema->components([
            Callout::make('FUEC anulado')
                ->danger()
                ->columnSpanFull()
                ->description(fn (Fuec $record) => $record->anulado_at?->timezone('America/Bogota')->format('d/m/Y h:i a').': '.$record->motivo_anulacion)
                ->visible(fn (Fuec $record) => $record->anulado()),

            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('numero')->label('FUEC No.')->fontFamily('mono')->weight('bold')->copyable()->columnSpan(2),
                    TextEntry::make('estado')->badge()->state(fn (Fuec $record) => $record->estado()),
                    TextEntry::make('vigencia')->label('Vigencia')
                        ->state(fn (Fuec $record) => $record->fecha_inicio->format('d/m/Y').' – '.$record->fecha_fin->format('d/m/Y'))
                        ->helperText(fn (Fuec $record) => 'Horario: '.$record->horario()->franjaTexto()),
                    TextEntry::make('contrato')->label('Contrato')
                        ->state(fn (Fuec $record) => ($record->datos['contrato']['numero'] ?? '—').' · '.($record->datos['contrato']['contratante'] ?? '—'))
                        ->url(fn (Fuec $record) => ContratoResource::getUrl('view', ['record' => $record->contrato_id]))
                        ->columnSpan(2),
                    TextEntry::make('objeto')->label('Objeto')->state(fn (Fuec $record) => $record->datos['contrato']['objeto'] ?? '—')->columnSpan(2),
                    TextEntry::make('ruta')->label('Origen – destino')
                        ->state(fn (Fuec $record) => ($record->datos['origen'] ?? '—').' – '.($record->datos['destino'] ?? '—'))->columnSpan(2),
                    TextEntry::make('convenio')->label('Convenio')
                        ->state(fn (Fuec $record) => $record->datos['convenio']['razon_social'] ?? 'No aplica'),
                    TextEntry::make('usuario.name')->label('Expidió')
                        ->helperText(fn (Fuec $record) => $record->created_at?->timezone('America/Bogota')->format('d/m/Y h:i a')),
                    TextEntry::make('recorrido')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('Vehículo')
                ->icon('heroicon-o-truck')
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 6])
                ->schema([
                    TextEntry::make('datos.vehiculo.placa')->label('Placa')
                        ->formatStateUsing(fn (string $state) => new HtmlString('<span class="ruta-placa">'.e($state).'</span>'))
                        ->url(fn (Fuec $record) => VehiculoResource::getUrl('view', ['record' => $record->vehiculo_id])),
                    TextEntry::make('datos.vehiculo.modelo')->label('Modelo'),
                    TextEntry::make('marca')->label('Marca')
                        ->state(fn (Fuec $record) => trim(($record->datos['vehiculo']['marca'] ?? '').' '.($record->datos['vehiculo']['linea'] ?? ''))),
                    TextEntry::make('datos.vehiculo.clase')->label('Clase'),
                    TextEntry::make('datos.vehiculo.numero_interno')->label('Interno')->placeholder('—'),
                    TextEntry::make('datos.vehiculo.tarjeta_operacion')->label('Tarjeta de operación')->placeholder('—'),
                ]),

            Section::make('Conductores')
                ->icon('heroicon-o-user-group')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('datos.conductores')
                        ->hiddenLabel()
                        ->columns(['default' => 2, 'md' => 4])
                        ->schema([
                            TextEntry::make('nombre')->weight('bold'),
                            TextEntry::make('documento')->label('Documento'),
                            TextEntry::make('categoria')->label('Licencia')->placeholder('—'),
                            TextEntry::make('licencia_vence')->label('Vence')->formatStateUsing(fn ($state) => $fecha($state)),
                        ]),
                ]),
        ]);
    }
}
