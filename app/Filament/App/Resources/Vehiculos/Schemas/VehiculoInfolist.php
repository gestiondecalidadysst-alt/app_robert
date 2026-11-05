<?php

namespace App\Filament\App\Resources\Vehiculos\Schemas;

use App\Enums\EstadoDocumento;
use App\Enums\TipoDocumentoVehiculo;
use App\Models\EmpresaConvenio;
use App\Models\Vehiculo;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Hoja de vida del vehículo: resumen, semáforo de documentos y datos de la tarjeta de propiedad.
 * Los documentos (con historial) y las novedades están en las pestañas inferiores (relation managers).
 */
final class VehiculoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('No puede operar')
                ->danger()
                ->icon('heroicon-o-exclamation-triangle')
                ->columnSpanFull()
                ->description(fn (Vehiculo $record) => new HtmlString(
                    collect($record->bloqueosOperacion())->map(fn (string $m) => '• '.e($m))->implode('<br>')
                ))
                ->visible(fn (Vehiculo $record) => ! $record->puedeOperar()),

            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    ImageEntry::make('foto_path')
                        ->hiddenLabel()
                        ->disk('public')
                        ->imageHeight(120)
                        ->visible(fn (Vehiculo $record) => filled($record->foto_path)),
                    Grid::make(['default' => 2, 'md' => 3])
                        ->columnSpan(fn (Vehiculo $record) => filled($record->foto_path) ? 3 : 4)
                        ->schema([
                            TextEntry::make('placa')
                                ->formatStateUsing(fn (string $state) => new HtmlString('<span class="ruta-placa">'.e($state).'</span>')),
                            TextEntry::make('estado')->badge()
                                ->helperText(fn (Vehiculo $record) => $record->estado_desde ? 'Desde '.$record->estado_desde->format('d/m/Y') : null),
                            TextEntry::make('numero_interno')->label('Número interno')->placeholder('—'),
                            TextEntry::make('descripcion')->label('Vehículo'),
                            TextEntry::make('claseVehiculo.nombre')->label('Clase'),
                            TextEntry::make('capacidad_pasajeros')->label('Pasajeros')->placeholder('—'),
                            TextEntry::make('propietario.nombre_completo')
                                ->label('Propietario')
                                ->helperText(fn (Vehiculo $record) => $record->propietario?->telefono),
                            TextEntry::make('conductores')
                                ->label('Conductores')
                                ->state(fn (Vehiculo $record) => $record->asignacionesVigentes
                                    ->sortByDesc('principal')
                                    ->map(fn ($a) => $a->conductor?->nombre_completo.($a->principal ? ' (principal)' : ''))
                                    ->implode(', '))
                                ->placeholder('Sin asignar')
                                ->columnSpan(2),
                        ]),
                ]),

            Section::make('Documentos vigentes')
                ->icon('heroicon-o-shield-check')
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 3, 'xl' => 6])
                ->schema(collect(TipoDocumentoVehiculo::cases())->map(
                    fn (TipoDocumentoVehiculo $tipo) => TextEntry::make("documento_{$tipo->value}")
                        ->label($tipo->corto())
                        ->state(fn (Vehiculo $record) => $record->documentoVigente($tipo)?->fecha_vencimiento?->format('d/m/Y') ?? 'Sin registrar')
                        ->badge()
                        ->color(fn (Vehiculo $record) => $record->estadoDocumento($tipo)->getColor())
                        ->helperText(fn (Vehiculo $record) => match ($record->estadoDocumento($tipo)) {
                            EstadoDocumento::Vencido => 'Vencido',
                            EstadoDocumento::PorVencer => 'Vence en '.$record->documentoVigente($tipo)->diasParaVencer().' días',
                            default => null,
                        })
                )->all()),

            Tabs::make('detalle')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Tarjeta de propiedad')
                        ->columns(['default' => 2, 'md' => 4])
                        ->schema([
                            TextEntry::make('marca.nombre')->label('Marca'),
                            TextEntry::make('linea.nombre')->label('Línea')->placeholder('—'),
                            TextEntry::make('modelo'),
                            TextEntry::make('color')->placeholder('—'),
                            TextEntry::make('carroceria.nombre')->label('Carrocería')->placeholder('—'),
                            TextEntry::make('combustible.nombre')->label('Combustible')->placeholder('—'),
                            TextEntry::make('tipoServicio.nombre')->label('Servicio')->placeholder('—'),
                            TextEntry::make('cilindraje')->suffix(' cc')->placeholder('—'),
                            TextEntry::make('capacidad_kg')->label('Capacidad')->suffix(' kg')->placeholder('—'),
                            TextEntry::make('puertas')->placeholder('—'),
                            TextEntry::make('numero_motor')->label('Motor')->placeholder('—'),
                            TextEntry::make('vin')->label('VIN')->placeholder('—'),
                            TextEntry::make('numero_chasis')->label('Chasis')->placeholder('—'),
                            TextEntry::make('fecha_matricula')->label('Matrícula')->date('d/m/Y')->placeholder('—'),
                            TextEntry::make('organismoTransito.nombre_completo')->label('Organismo de tránsito')->placeholder('—'),
                        ]),
                    Tab::make('Ficha técnica')
                        ->columns(['default' => 2, 'md' => 4])
                        ->schema(collect(Vehiculo::FICHA_TECNICA)->map(
                            fn (string $etiqueta, string $clave) => TextEntry::make("ficha_tecnica.{$clave}")->label($etiqueta)->placeholder('—')
                        )->values()->all()),
                    Tab::make('Vinculación')
                        ->columns(['default' => 2, 'md' => 4])
                        ->schema([
                            TextEntry::make('tipo_vinculacion')
                                ->label('Tipo')
                                ->formatStateUsing(fn (?string $state) => EmpresaConvenio::TIPOS_VINCULACION[$state] ?? $state)
                                ->placeholder('—'),
                            TextEntry::make('empresaConvenio.razon_social')->label('Empresa en convenio')->placeholder('—'),
                            TextEntry::make('fecha_vinculacion')->label('Fecha')->date('d/m/Y')->placeholder('—'),
                            TextEntry::make('observaciones')->placeholder('—')->columnSpanFull(),
                        ]),
                ]),
        ]);
    }
}
