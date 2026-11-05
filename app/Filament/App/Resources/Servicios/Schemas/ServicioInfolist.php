<?php

namespace App\Filament\App\Resources\Servicios\Schemas;

use App\Enums\TipoPuntoRuta;
use App\Filament\App\Resources\Fuecs\FuecResource;
use App\Filament\App\Resources\Rutas\RutaResource;
use App\Models\Empresa;
use App\Models\Ruta;
use App\Models\Servicio;
use App\Support\Operacion\Jornada;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

final class ServicioInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('Servicio cancelado')->danger()->columnSpanFull()
                ->description(fn (Servicio $record) => $record->motivo_cancelacion)
                ->visible(fn (Servicio $record) => $record->cancelado()),

            Callout::make('Avisos de jornada y disponibilidad')->warning()->columnSpanFull()
                ->icon('heroicon-o-exclamation-triangle')
                ->description(fn (Servicio $record) => new HtmlString(collect(self::avisos($record))->map(fn ($m) => '• '.e($m))->implode('<br>')))
                ->visible(fn (Servicio $record) => ! $record->cancelado() && self::avisos($record) !== []),

            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('ruta_texto')->label('Origen → destino')->weight('bold')->columnSpan(2)
                        ->helperText(fn (Servicio $record) => $record->recorrido),
                    TextEntry::make('estado')->badge(),
                    TextEntry::make('fuec_id')->label('Extracto')->placeholder('Sin extracto')
                        ->formatStateUsing(fn (Servicio $record) => $record->fuec?->numero_formateado)
                        ->url(fn (Servicio $record) => $record->fuec_id ? FuecResource::getUrl('view', ['record' => $record->fuec_id]) : null),
                    TextEntry::make('fecha_inicio')->label('Fechas')
                        ->formatStateUsing(fn (Servicio $record) => $record->horario()->texto()),
                    TextEntry::make('vehiculo.placa')->label('Vehículo')->placeholder('Sin asignar'),
                    TextEntry::make('conductores')->label('Conductores')->placeholder('Sin asignar')
                        ->state(fn (Servicio $record) => $record->conductores->map->nombre_completo->implode(', ') ?: null),
                    TextEntry::make('contrato.cliente.nombre')->label('Contrato')->placeholder('Sin contrato')
                        ->formatStateUsing(fn (Servicio $record) => $record->contrato ? "{$record->contrato->numero_formateado} · {$record->contrato->cliente?->nombre}" : null),
                    TextEntry::make('conduccion')->label('Conducción estimada por día')
                        ->state(fn (Servicio $record) => $record->minutosConduccion() ? Jornada::horas($record->minutosConduccion()) : 'Sin estimar'),
                    TextEntry::make('pasajeros')->placeholder('—'),
                    TextEntry::make('created_at')->label('Planificado')
                        ->formatStateUsing(fn (Servicio $record) => $record->created_at->timezone('America/Bogota')->format('d/m/Y h:i a').' · '.self::antelacion($record))
                        ->helperText(fn (Servicio $record) => $record->usuario?->name),
                    TextEntry::make('leido_at')->label('Plan leído por el conductor')->placeholder('Aún no')
                        ->formatStateUsing(fn (Servicio $record) => $record->leido_at->timezone('America/Bogota')->format('d/m/Y h:i a').' · '.$record->leidoPor?->nombre_completo),
                    TextEntry::make('observaciones')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make(fn (Servicio $record) => $record->ruta ? 'Rutograma: '.$record->ruta->nombre : 'Rutograma')
                ->icon('heroicon-o-map')
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 4])
                ->collapsible()
                ->schema([
                    TextEntry::make('sin_ruta')->hiddenLabel()->columnSpanFull()
                        ->state(new HtmlString('Este servicio no tiene rutograma. Cree uno para este origen y destino en <a href="'.RutaResource::getUrl('create').'" style="color:rgb(194 65 12);font-weight:600">Operación → Rutogramas</a> y edite el plan.'))
                        ->visible(fn (Servicio $record) => ! $record->ruta),
                    TextEntry::make('ruta.distancia_km')->label('Distancia')->suffix(' km')->placeholder('—')->visible(fn (Servicio $record) => (bool) $record->ruta),
                    TextEntry::make('ruta.duracion_min')->label('Conducción')->formatStateUsing(fn ($state) => Ruta::duracionTexto($state))->placeholder('—')->visible(fn (Servicio $record) => (bool) $record->ruta),
                    TextEntry::make('ruta.velocidad_maxima')->label('Velocidad máxima')->suffix(' km/h')->placeholder('—')->visible(fn (Servicio $record) => (bool) $record->ruta),
                    TextEntry::make('puntos')->label('Puntos')->visible(fn (Servicio $record) => (bool) $record->ruta)
                        ->state(fn (Servicio $record) => $record->ruta?->puntosDe(TipoPuntoRuta::Critico)->count().' críticos · '.$record->ruta?->puntosDe(TipoPuntoRuta::Parada)->count().' paradas'),
                ]),

            Section::make('Seguimiento del viaje')
                ->description('Lo que reporta el conductor desde el portal (opcional).')
                ->icon('heroicon-o-signal')
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('eventos')->hiddenLabel()->view('filament.operacion.eventos-servicio'),
                ]),
        ]);
    }

    /** @return list<string> */
    public static function avisos(Servicio $record): array
    {
        return Jornada::avisos($record, $record->conductores->pluck('id')->all());
    }

    public static function antelacion(Servicio $s): string
    {
        $horas = $s->horasDeAntelacion();
        $minimo = (int) (Empresa::query()->whereKey($s->empresa_id)->value('planeacion_antelacion_horas') ?? 24);

        return match (true) {
            $horas < 0 => 'después de la salida',
            $horas < $minimo => "con {$horas} h de antelación (menos de {$minimo} h)",
            default => 'con '.($horas >= 48 ? round($horas / 24).' días' : "{$horas} h").' de antelación',
        };
    }
}
