<?php

namespace App\Filament\App\Resources\Mantenimientos;

use App\Actions\Mantenimiento\AnularMantenimiento;
use App\Enums\AccionMantenimiento;
use App\Enums\TipoMantenimiento;
use App\Filament\App\Resources\Mantenimientos\Pages\ListarMantenimientos;
use App\Filament\App\Resources\Mantenimientos\Pages\RegistrarMantenimiento;
use App\Filament\App\Resources\Mantenimientos\Pages\VerMantenimiento;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\Evidencia;
use App\Models\Mantenimiento;
use App\Models\PlanAccion;
use App\Models\Vehiculo;
use App\Support\Mantenimiento\PlanPreventivo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Mantenimientos hechos a los vehículos (los realiza el propietario). Los registran el auxiliar,
 * el rol Mantenimiento o el conductor desde su portal, con evidencias. No se editan: se anulan.
 */
class MantenimientoResource extends Resource
{
    protected static ?string $model = Mantenimiento::class;

    protected static ?string $slug = 'mantenimientos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Mantenimientos';

    protected static ?string $breadcrumb = 'Mantenimientos';

    protected static ?string $modelLabel = 'mantenimiento';

    protected static ?string $pluralModelLabel = 'mantenimientos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vehículo y fecha')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Select::make('vehiculo_id')->label('Vehículo')->required()->searchable()->live()
                        ->options(fn () => Vehiculo::query()->enFlota()->orderBy('placa')->get()
                            ->mapWithKeys(fn (Vehiculo $v) => [$v->id => $v->placa . ($v->numero_interno ? " · int. {$v->numero_interno}" : '')])),
                    DatePicker::make('fecha')->required()->default(today())->maxDate(today()),
                    TextInput::make('kilometraje')->numeric()->minValue(0)->maxValue(9999999)
                        ->helperText(function (Get $get) {
                            $km = $get('vehiculo_id') ? Vehiculo::query()->find($get('vehiculo_id'))?->kilometrajeActual() : null;

                            return $km ? 'Último reportado: ' . number_format($km, 0, ',', '.') . ' km.' : 'Obligatorio si cumple rutinas preventivas.';
                        }),
                    ToggleButtons::make('tipo')->options(TipoMantenimiento::class)->inline()->required()->live(),
                    Select::make('accion')->label('Acción')->options(AccionMantenimiento::class)->required(),
                    Select::make('plan_accion_id')->label('Atiende el plan de acción')
                        ->options(fn (Get $get) => $get('vehiculo_id')
                            ? PlanAccion::query()->where('vehiculo_id', $get('vehiculo_id'))->pendientes()->latest('id')->get()
                                ->mapWithKeys(fn (PlanAccion $p) => [$p->id => "{$p->numero} · {$p->titulo}"])
                            : [])
                        ->placeholder('Ninguno')
                        ->helperText('El plan pasa a «Por verificar».'),
                ]),
            Section::make('Trabajo realizado')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Textarea::make('actividad')->label('Actividad realizada')->required()->rows(3),
                    Textarea::make('problema')->label('Problema presentado')->rows(3),
                    Textarea::make('repuestos')->label('Repuestos (nombre y referencia)')->rows(2),
                    CheckboxList::make('rutinas')->label('Rutinas preventivas que cumple')
                        ->options(fn (Get $get) => $get('vehiculo_id') && ($vehiculo = Vehiculo::query()->find($get('vehiculo_id')))
                            ? PlanPreventivo::para($vehiculo)->mapWithKeys(fn (array $f) => [$f['rutina']->id => $f['rutina']->nombre . ' · ' . PlanPreventivo::ETIQUETAS[$f['estado']]])->all()
                            : [])
                        ->visible(fn (Get $get) => filled($get('vehiculo_id')))
                        ->helperText('Desde este mantenimiento se calcula el próximo.')
                        ->columns(1),
                ]),
            Section::make('Taller, costo y evidencias')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('taller')->label('Taller / centro automotriz')->maxLength(255),
                    TextInput::make('responsable_documento')->label('Documento del responsable')->maxLength(30),
                    TextInput::make('responsable_nombre')->label('Nombre del responsable')->maxLength(255),
                    TextInput::make('valor')->label('Valor')->numeric()->minValue(0)->prefix('$'),
                    TextInput::make('factura_numero')->label('No. de factura')->maxLength(60),
                    FileUpload::make('archivos')->label('Evidencias (factura, fotos)')
                        ->multiple()->maxFiles(10)->storeFiles(false)
                        ->acceptedFileTypes(Evidencia::TIPOS)->maxSize(Evidencia::MAX_KB)
                        ->helperText('PDF o imágenes, hasta 10 MB cada una.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehiculo', 'conductor', 'usuario'])->withCount('evidencias'))
            ->defaultSort('fecha', 'desc')
            ->columns([
                TextColumn::make('fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('vehiculo.placa')->label('Vehículo')->weight('bold')->searchable(),
                TextColumn::make('tipo')->badge(),
                TextColumn::make('accion')->label('Acción'),
                TextColumn::make('actividad')->limit(60)->wrap()->searchable(),
                TextColumn::make('kilometraje')->numeric(thousandsSeparator: '.')->placeholder('—')->toggleable(),
                TextColumn::make('valor')->money('COP', locale: 'es_CO')->placeholder('—')->toggleable(),
                TextColumn::make('evidencias_count')->label('Evidencias')->badge()->color(fn (int $state) => $state ? 'success' : 'gray'),
                TextColumn::make('registro')->label('Registró')
                    ->state(fn (Mantenimiento $r) => $r->conductor ? 'Conductor · ' . $r->conductor->nombre_completo : ($r->usuario?->name ?? '—'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('anulado_at')->label('Anulado')->date('d/m/Y')->placeholder('—')->color('danger')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('inicial')->label('Puntos de partida del plan')
                    ->queries(true: fn (Builder $query) => $query->where('registro_inicial', true), false: fn (Builder $query) => $query->where('registro_inicial', false))
                    ->default(false),
                TernaryFilter::make('anulados')->label('Anulados')
                    ->queries(true: fn (Builder $query) => $query->whereNotNull('anulado_at'), false: fn (Builder $query) => $query->whereNull('anulado_at'))
                    ->default(false),
                SelectFilter::make('vehiculo_id')->label('Vehículo')->searchable()
                    ->options(fn () => Vehiculo::query()->orderBy('placa')->pluck('placa', 'id')),
                SelectFilter::make('tipo')->options(TipoMantenimiento::class),
                Filter::make('fechas')
                    ->schema([DatePicker::make('desde'), DatePicker::make('hasta')])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $d) => $q->whereDate('fecha', '>=', $d))
                        ->when($data['hasta'] ?? null, fn ($q, $d) => $q->whereDate('fecha', '<=', $d))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('vehiculo.placa')->label('Vehículo'),
                    TextEntry::make('fecha')->date('d/m/Y'),
                    TextEntry::make('kilometraje')->numeric(thousandsSeparator: '.')->placeholder('—'),
                    TextEntry::make('tipo')->badge(),
                    TextEntry::make('accion')->label('Acción'),
                    TextEntry::make('valor')->money('COP', locale: 'es_CO')->placeholder('—'),
                    TextEntry::make('factura_numero')->label('Factura')->placeholder('—'),
                    TextEntry::make('taller')->placeholder('—'),
                    TextEntry::make('responsable')->label('Responsable')
                        ->state(fn (Mantenimiento $r) => trim(($r->responsable_nombre ?? '') . ($r->responsable_documento ? ' · ' . $r->responsable_documento : '')) ?: null)
                        ->placeholder('—'),
                    TextEntry::make('plan.numero')->label('Plan de acción')->placeholder('—')
                        ->url(fn (Mantenimiento $r) => $r->plan ? \App\Filament\App\Resources\PlanesAccion\PlanAccionResource::getUrl('view', ['record' => $r->plan]) : null),
                    TextEntry::make('registro')->label('Registró')
                        ->state(fn (Mantenimiento $r) => $r->conductor ? 'Conductor · ' . $r->conductor->nombre_completo . ' (portal)' : ($r->usuario?->name ?? '—'))
                        ->helperText(fn (Mantenimiento $r) => $r->created_at->format('d/m/Y h:i a')),
                    TextEntry::make('rutinas.nombre')->label('Rutinas cumplidas')->badge()->placeholder('—')->columnSpan(['md' => 2]),
                    TextEntry::make('actividad')->label('Actividad realizada')->columnSpanFull(),
                    TextEntry::make('problema')->label('Problema presentado')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('repuestos')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('motivo_anulacion')->label('Anulado')->color('danger')->columnSpanFull()
                        ->visible(fn (Mantenimiento $r) => $r->anulado())
                        ->state(fn (Mantenimiento $r) => $r->anulado_at?->format('d/m/Y') . ' · ' . $r->motivo_anulacion),
                ]),
            Section::make('Evidencias')
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('evidencias')->hiddenLabel()->view('filament.mantenimiento.evidencias-entry'),
                ]),
        ]);
    }

    public static function accionAnular(): Action
    {
        return Action::make('anular')
            ->label('Anular')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (Mantenimiento $record) => ! $record->anulado() && auth()->user()?->can('anular_mantenimientos'))
            ->schema([Textarea::make('motivo')->required()->rows(2)])
            ->modalDescription('Deja de contar para el plan preventivo. No se borra.')
            ->action(function (Mantenimiento $record, array $data, Action $action): void {
                ErroresDeNegocio::intentar($action, fn () => app(AnularMantenimiento::class)($record, $data['motivo'], auth()->user()));
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarMantenimientos::route('/'),
            'create' => RegistrarMantenimiento::route('/registrar'),
            'view' => VerMantenimiento::route('/{record}'),
        ];
    }
}
