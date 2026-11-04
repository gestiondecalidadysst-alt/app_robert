<?php

namespace App\Filament\App\Resources\Rutas;

use App\Enums\TipoPuntoRuta;
use App\Filament\App\Resources\Rutas\Pages\CrearRuta;
use App\Filament\App\Resources\Rutas\Pages\EditarRuta;
use App\Filament\App\Resources\Rutas\Pages\ListarRutas;
use App\Filament\Compartido\CamposComunes;
use App\Models\Ruta;
use App\Support\Pdf\PdfPlanViaje;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Rutogramas (PESV paso 15): rutas frecuentes con puntos críticos, paradas seguras, velocidades,
 * rutas alternas y contactos de emergencia. El planeador sugiere el que corresponde al origen y destino.
 */
class RutaResource extends Resource
{
    protected static ?string $model = Ruta::class;

    protected static ?string $slug = 'rutogramas';

    protected static ?string $tenantRelationshipName = 'rutas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'Rutogramas';

    protected static ?string $modelLabel = 'rutograma';

    protected static ?string $pluralModelLabel = 'rutogramas';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ruta')
                ->icon('heroicon-o-map')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    CamposComunes::municipio('origen_municipio_id', 'Origen')->required()->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::nombrePorDefecto($get, $set))->columnSpan(['md' => 2]),
                    CamposComunes::municipio('destino_municipio_id', 'Destino')->required()->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::nombrePorDefecto($get, $set))->columnSpan(['md' => 2]),
                    TextInput::make('nombre')->required()->maxLength(150)->columnSpan(['md' => 2])
                        ->helperText('Ej.: Montería – Cereté por la vía 21.'),
                    Toggle::make('ida_y_vuelta')->label('Sirve también para el regreso')->default(true)->inline(false)->columnSpan(['md' => 1]),
                    Toggle::make('activo')->default(true)->inline(false)->columnSpan(['md' => 1]),
                    TextInput::make('distancia_km')->label('Distancia (km)')->numeric()->minValue(1),
                    TextInput::make('duracion_min')->label('Conducción estimada (min)')->numeric()->minValue(1)->maxValue(1440)
                        ->helperText('Se usa para el control de jornada.'),
                    TextInput::make('velocidad_maxima')->label('Velocidad máxima (km/h)')->numeric()->minValue(10)->maxValue(120)
                        ->helperText('La de la política de la empresa para esta ruta.'),
                    Textarea::make('recorrido')->label('Vías del recorrido')->rows(2)->columnSpanFull(),
                ]),
            Section::make('Puntos del recorrido')
                ->description('En el orden en que se encuentran: puntos críticos (siniestralidad, curvas, zonas escolares, tramos sin señal), paradas seguras (descanso, alimentación, combustible, alojamiento), puntos de control y apoyos en emergencia.')
                ->icon('heroicon-o-map-pin')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('puntos')->hiddenLabel()
                        ->schema([
                            Select::make('tipo')->options(TipoPuntoRuta::class)->required()->default(TipoPuntoRuta::Critico->value),
                            TextInput::make('nombre')->label('Lugar')->required()->maxLength(150),
                            TextInput::make('referencia')->label('Referencia (km, sector)')->maxLength(100),
                            TextInput::make('velocidad')->label('Velocidad (km/h)')->numeric()->minValue(5)->maxValue(120),
                            Textarea::make('indicacion')->label('Indicación al conductor')->rows(1)->columnSpanFull(),
                        ])
                        ->columns(['default' => 1, 'md' => 4])
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => filled($state['nombre'] ?? null)
                            ? (self::tipo($state['tipo'] ?? null)?->getLabel() ?? 'Punto') . ' · ' . $state['nombre'] : null)
                        ->addActionLabel('Agregar punto')
                        ->defaultItems(0),
                ]),
            Section::make('Contingencias')
                ->icon('heroicon-o-lifebuoy')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Textarea::make('rutas_alternas')->label('Rutas alternas')->rows(3),
                    Textarea::make('recomendaciones')->label('Recomendaciones (clima, horarios, fatiga, distracción)')->rows(3),
                    Textarea::make('contactos_emergencia')->label('Contactos de emergencia en la vía')->rows(3)
                        ->helperText('Ej.: Policía de Carreteras #767, hospital más cercano, grúa.'),
                ]),
        ]);
    }

    private static function tipo(mixed $valor): ?TipoPuntoRuta
    {
        return $valor instanceof TipoPuntoRuta ? $valor : TipoPuntoRuta::tryFrom((string) $valor);
    }

    private static function nombrePorDefecto(Get $get, Set $set): void
    {
        if (filled($get('nombre')) || ! $get('origen_municipio_id') || ! $get('destino_municipio_id')) {
            return;
        }
        $m = \App\Models\Municipio::query()->whereKey([$get('origen_municipio_id'), $get('destino_municipio_id')])->pluck('nombre', 'id');
        $set('nombre', mb_convert_case(mb_strtolower($m[$get('origen_municipio_id')] ?? ''), MB_CASE_TITLE) . ' – ' . mb_convert_case(mb_strtolower($m[$get('destino_municipio_id')] ?? ''), MB_CASE_TITLE));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['origen', 'destino']))
            ->defaultSort('nombre')
            ->emptyStateHeading('Sin rutogramas')
            ->emptyStateDescription('Cree las rutas frecuentes: el planeador sugiere la que corresponde al origen y destino de cada servicio.')
            ->columns([
                TextColumn::make('nombre')->searchable()->weight('bold')
                    ->description(fn (Ruta $r) => $r->origen?->nombre . ' → ' . $r->destino?->nombre . ($r->ida_y_vuelta ? ' (ida y vuelta)' : '')),
                TextColumn::make('distancia_km')->label('Distancia')->suffix(' km')->placeholder('—')->alignEnd(),
                TextColumn::make('duracion_min')->label('Conducción')->formatStateUsing(fn ($state) => Ruta::duracionTexto($state))->placeholder('—'),
                TextColumn::make('velocidad_maxima')->label('Vel. máx.')->suffix(' km/h')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('puntos')->label('Puntos')->visibleFrom('md')
                    ->state(fn (Ruta $r) => $r->puntosDe(TipoPuntoRuta::Critico)->count() . ' críticos · ' . $r->puntosDe(TipoPuntoRuta::Parada)->count() . ' paradas'),
                IconColumn::make('activo')->boolean(),
            ])
            ->filters([TernaryFilter::make('activo')->default(true)])
            ->recordActions([
                Action::make('pdf')->label('Rutograma')->icon('heroicon-o-arrow-down-tray')->color('gray')
                    ->action(fn (Ruta $record) => response()->streamDownload(fn () => print (PdfPlanViaje::rutograma($record)), 'rutograma-' . \Illuminate\Support\Str::slug($record->nombre) . '.pdf', ['Content-Type' => 'application/pdf'])),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarRutas::route('/'),
            'create' => CrearRuta::route('/crear'),
            'edit' => EditarRuta::route('/{record}/editar'),
        ];
    }
}
