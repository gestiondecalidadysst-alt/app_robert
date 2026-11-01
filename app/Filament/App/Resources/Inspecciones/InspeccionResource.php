<?php

namespace App\Filament\App\Resources\Inspecciones;

use App\Enums\ResultadoInspeccion;
use App\Enums\TipoInspeccion;
use App\Filament\App\Resources\Inspecciones\Pages\ListarInspecciones;
use App\Filament\App\Resources\Inspecciones\Pages\PendientesHoy;
use App\Filament\App\Resources\Inspecciones\Pages\RegistrarInspeccionMensual;
use App\Filament\App\Resources\Inspecciones\Pages\VerInspeccion;
use App\Models\Conductor;
use App\Models\Inspeccion;
use App\Models\Vehiculo;
use App\Support\Pdf\PdfInspeccion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Preoperacionales (los registra el conductor desde su portal) e inspecciones mensuales (las
 * registra la empresa). Son evidencia: no se editan ni se borran.
 */
class InspeccionResource extends Resource
{
    protected static ?string $model = Inspeccion::class;

    protected static ?string $slug = 'inspecciones';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Seguridad vial';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Preoperacionales e inspecciones';

    protected static ?string $breadcrumb = 'Preoperacionales e inspecciones';

    protected static ?string $modelLabel = 'inspección';

    protected static ?string $pluralModelLabel = 'preoperacionales e inspecciones';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('vehiculo'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('fecha')->date('d/m/Y')->sortable()
                    ->description(fn (Inspeccion $r) => $r->created_at->format('h:i a')),
                TextColumn::make('tipo')->formatStateUsing(fn (TipoInspeccion $state) => $state->corto())->badge()->color('gray'),
                TextColumn::make('placa')->label('Vehículo')->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('vehiculo', fn ($v) => $v->where('placa', 'like', '%' . strtoupper($search) . '%'))),
                TextColumn::make('conductor_nombre')->label('Conductor')->placeholder('—')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('conductor', fn ($c) => $c->where('nombres', 'like', "%{$search}%")->orWhere('apellidos', 'like', "%{$search}%")->orWhere('documento', 'like', "%{$search}%"))),
                TextColumn::make('kilometraje')->numeric(thousandsSeparator: '.')->placeholder('—')->toggleable(),
                TextColumn::make('resultado')->badge(),
                TextColumn::make('fallas')->label('No cumple')
                    ->state(fn (Inspeccion $r) => count($r->fallas()))
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('resultado')->options(ResultadoInspeccion::class),
                SelectFilter::make('vehiculo_id')->label('Vehículo')
                    ->options(fn () => Vehiculo::query()->orderBy('placa')->pluck('placa', 'id'))->searchable(),
                SelectFilter::make('conductor_id')->label('Conductor')
                    ->options(fn () => Conductor::query()->orderBy('nombres')->get()->pluck('nombre_completo', 'id'))->searchable(),
                Filter::make('fechas')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $d) => $q->whereDate('fecha', '>=', $d))
                        ->when($data['hasta'] ?? null, fn ($q, $d) => $q->whereDate('fecha', '<=', $d)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['desde'] ?? null) ? 'Desde ' . \Illuminate\Support\Carbon::parse($data['desde'])->format('d/m/Y') : null,
                        ($data['hasta'] ?? null) ? 'Hasta ' . \Illuminate\Support\Carbon::parse($data['hasta'])->format('d/m/Y') : null,
                    ])),
            ])
            ->recordActions([
                ViewAction::make(),
                self::accionPdf()->iconButton()->tooltip('PDF'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('resultado')->badge(),
                    TextEntry::make('tipo')->formatStateUsing(fn (TipoInspeccion $state) => $state->corto()),
                    TextEntry::make('created_at')->label('Registrada')->dateTime('d/m/Y h:i a'),
                    TextEntry::make('kilometraje')->numeric(thousandsSeparator: '.')->placeholder('—'),
                    TextEntry::make('placa')->label('Vehículo')
                        ->helperText(fn (Inspeccion $r) => trim(($r->datos['vehiculo']['marca'] ?? '') . ' ' . ($r->datos['vehiculo']['linea'] ?? '') . ' ' . ($r->datos['vehiculo']['modelo'] ?? ''))),
                    TextEntry::make('conductor_nombre')->label('Conductor')->placeholder('—')
                        ->helperText(fn (Inspeccion $r) => ($r->datos['conductor']['documento'] ?? null) ? 'C.C. ' . $r->datos['conductor']['documento'] : null),
                    TextEntry::make('fuec')->label('FUEC')->state(fn (Inspeccion $r) => $r->datos['fuec']['numero'] ?? null)->placeholder('Sin servicio'),
                    TextEntry::make('registrado')->label('Registrada por')
                        ->state(fn (Inspeccion $r) => ($r->datos['registrado_por_conductor'] ?? false) ? 'El conductor (portal)' : ($r->datos['registrado_por'] ?? '—')),
                    TextEntry::make('observaciones')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Ítems')
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('respuestas')->hiddenLabel()->view('filament.inspecciones.respuestas'),
                ]),
            Section::make('Evidencia')
                ->columnSpanFull()
                ->collapsible()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    ImageEntry::make('firma')->label('Firma estampada')
                        ->state(fn (Inspeccion $r) => \App\Support\Pdf\Imagenes::deDisco($r->firma_path, Inspeccion::DISCO))
                        ->imageHeight(70)
                        ->extraImgAttributes(['style' => 'background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:4px'])
                        ->placeholder('—'),
                    Grid::make(1)->schema([
                        TextEntry::make('ip')->label('IP')->placeholder('—'),
                        TextEntry::make('ubicacion')->label('Ubicación')
                            ->state(fn (Inspeccion $r) => $r->latitud !== null ? number_format($r->latitud, 5) . ', ' . number_format($r->longitud, 5) : null)
                            ->url(fn (Inspeccion $r) => $r->latitud !== null ? "https://www.google.com/maps?q={$r->latitud},{$r->longitud}" : null, true)
                            ->placeholder('No compartida'),
                    ]),
                    TextEntry::make('user_agent')->label('Dispositivo')->placeholder('—')->limit(160),
                ]),
        ]);
    }

    public static function accionPdf(): Action
    {
        return Action::make('pdf')
            ->label('Descargar PDF')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn (Inspeccion $record) => response()->streamDownload(
                fn () => print (PdfInspeccion::generar($record)),
                PdfInspeccion::nombreArchivo($record),
                ['Content-Type' => 'application/pdf'],
            ));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarInspecciones::route('/'),
            'pendientes' => PendientesHoy::route('/pendientes-hoy'),
            'mensual' => RegistrarInspeccionMensual::route('/mensual'),
            'view' => VerInspeccion::route('/{record}'),
        ];
    }
}
