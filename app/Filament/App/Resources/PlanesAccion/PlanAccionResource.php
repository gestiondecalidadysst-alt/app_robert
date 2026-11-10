<?php

namespace App\Filament\App\Resources\PlanesAccion;

use App\Actions\Mantenimiento\GestionarPlanAccion;
use App\Enums\EstadoPlan;
use App\Enums\OrigenPlan;
use App\Enums\PrioridadPlan;
use App\Filament\App\Resources\Mantenimientos\MantenimientoResource;
use App\Filament\App\Resources\PlanesAccion\Pages\ListarPlanesAccion;
use App\Filament\App\Resources\PlanesAccion\Pages\VerPlanAccion;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\Evidencia;
use App\Models\PlanAccion;
use App\Models\Vehiculo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Planes de acción: fallas de preoperacionales e inspecciones, rutinas preventivas vencidas y
 * hallazgos registrados a mano. Los ejecuta el propietario; la empresa los verifica y los cierra.
 */
class PlanAccionResource extends Resource
{
    protected static ?string $model = PlanAccion::class;

    protected static ?string $slug = 'planes-accion';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Planes de acción';

    protected static ?string $breadcrumb = 'Planes de acción';

    protected static ?string $modelLabel = 'plan de acción';

    protected static ?string $pluralModelLabel = 'planes de acción';

    public static function getNavigationBadge(): ?string
    {
        $n = PlanAccion::query()->pendientes()->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return PlanAccion::query()->pendientes()->where('prioridad', PrioridadPlan::Alta->value)->exists() ? 'danger' : 'warning';
    }

    /** Campos para crear un plan a mano. */
    public static function camposManual(): array
    {
        return [
            Select::make('vehiculo_id')->label('Vehículo')->required()->searchable()
                ->options(fn () => Vehiculo::query()->enFlota()->orderBy('placa')->pluck('placa', 'id')),
            Select::make('prioridad')->options(PrioridadPlan::class)->required()->default(PrioridadPlan::Media->value)
                ->helperText('Alta: el vehículo no queda apto en el preoperacional hasta cerrar el plan.'),
            TextInput::make('titulo')->label('Hallazgo')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('detalle')->rows(2)->columnSpanFull(),
            Textarea::make('accion')->label('Acción a realizar')->rows(2)->columnSpanFull(),
            TextInput::make('responsable')->helperText('Vacío = el propietario del vehículo.'),
            DatePicker::make('fecha_limite')->label('Fecha límite')->minDate(today()),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('vehiculo')->withCount('evidencias'))
            ->defaultSort(fn (Builder $query) => $query->orderByRaw("case prioridad when 'alta' then 0 when 'media' then 1 else 2 end")->orderByDesc('id'))
            ->columns([
                TextColumn::make('numero')->label('No.')->weight('bold')->formatStateUsing(fn (PlanAccion $record) => $record->numero_formateado),
                TextColumn::make('vehiculo.placa')->label('Vehículo')->weight('bold')->searchable(),
                TextColumn::make('titulo')->label('Hallazgo')->wrap()->limit(70)->searchable()
                    ->description(fn (PlanAccion $r) => $r->origen->getLabel().($r->reincidencias ? " · {$r->reincidencias} reincidencia(s)" : '')),
                TextColumn::make('prioridad')->badge(),
                TextColumn::make('estado')->badge(),
                TextColumn::make('fecha_limite')->label('Límite')->date('d/m/Y')->placeholder('—')
                    ->color(fn (PlanAccion $r) => $r->vencido() ? 'danger' : null)
                    ->description(fn (PlanAccion $r) => $r->vencido() ? 'Vencido' : null),
                TextColumn::make('responsable')->toggleable()->limit(30),
                TextColumn::make('evidencias_count')->label('Evid.')->badge()->color(fn (int $state) => $state ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('prioridad')->options(PrioridadPlan::class),
                SelectFilter::make('origen')->options(OrigenPlan::class),
                SelectFilter::make('vehiculo_id')->label('Vehículo')->searchable()
                    ->options(fn () => Vehiculo::query()->orderBy('placa')->pluck('placa', 'id')),
            ])
            ->recordActions([ViewAction::make()->iconButton()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('estado')->badge(),
                    TextEntry::make('prioridad')->badge()
                        ->helperText(fn (PlanAccion $r) => $r->prioridad === PrioridadPlan::Alta && $r->pendiente() ? 'El vehículo no queda apto hasta cerrarlo.' : null),
                    TextEntry::make('vehiculo.placa')->label('Vehículo'),
                    TextEntry::make('fecha_limite')->label('Fecha límite')->date('d/m/Y')->placeholder('—')
                        ->color(fn (PlanAccion $r) => $r->vencido() ? 'danger' : null),
                    TextEntry::make('origen')->formatStateUsing(fn (OrigenPlan $state) => $state->getLabel()),
                    TextEntry::make('responsable')->placeholder('—'),
                    TextEntry::make('created_at')->label('Abierto')->dateTime('d/m/Y h:i a'),
                    TextEntry::make('reincidencias'),
                    TextEntry::make('accion')->label('Acción a realizar')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('observacion_cierre')
                        ->label(fn (PlanAccion $r) => $r->estado === EstadoPlan::Rechazado ? 'Motivo del rechazo' : 'Cierre')
                        ->visible(fn (PlanAccion $r) => filled($r->observacion_cierre))
                        ->helperText(fn (PlanAccion $r) => $r->cerrado_at ? 'Cerrado el '.$r->cerrado_at->format('d/m/Y h:i a').($r->cerradoPor ? ' por '.$r->cerradoPor->name : '') : null)
                        ->columnSpanFull(),
                ]),
            Section::make('Hallazgos')
                ->columnSpanFull()
                ->schema([ViewEntry::make('hallazgos')->hiddenLabel()->view('filament.mantenimiento.hallazgos')]),
            Section::make('Mantenimientos y evidencias')
                ->columnSpanFull()
                ->schema([ViewEntry::make('evidencias')->hiddenLabel()->view('filament.mantenimiento.plan-evidencias')]),
        ]);
    }

    /** @return list<Action> */
    public static function accionesGestion(): array
    {
        $puede = fn () => auth()->user()?->can('editar_planes_accion');

        return [
            Action::make('mantenimiento')
                ->label('Registrar mantenimiento')
                ->icon('heroicon-o-wrench-screwdriver')
                ->visible(fn (PlanAccion $record) => $record->pendiente() && auth()->user()?->can('crear_mantenimientos'))
                ->url(fn (PlanAccion $record) => MantenimientoResource::getUrl('create', ['plan' => $record->id])),
            Action::make('evidencia')
                ->label('Adjuntar evidencia')
                ->icon('heroicon-o-paper-clip')
                ->color('gray')
                ->visible(fn (PlanAccion $record) => $record->pendiente() && $puede())
                ->schema([
                    FileUpload::make('archivos')->label('Archivos')->multiple()->maxFiles(10)->storeFiles(false)->required()
                        ->acceptedFileTypes(Evidencia::TIPOS)->maxSize(Evidencia::MAX_KB),
                    Textarea::make('nota')->rows(2),
                ])
                ->modalDescription('El plan pasa a «Por verificar».')
                ->action(function (PlanAccion $record, array $data, Action $action): void {
                    ErroresDeNegocio::intentar($action, fn () => GestionarPlanAccion::adjuntarEvidencia($record, array_values((array) $data['archivos']), $data['nota'] ?? null, auth()->user()));
                    Notification::make()->success()->title('Evidencia adjuntada')->send();
                }),
            Action::make('iniciar')
                ->label('En ejecución')
                ->icon('heroicon-o-play')
                ->color('gray')
                ->visible(fn (PlanAccion $record) => $record->estado === EstadoPlan::Abierto && $puede())
                ->action(fn (PlanAccion $record) => GestionarPlanAccion::iniciar($record)),
            Action::make('rechazar')
                ->label('Rechazar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (PlanAccion $record) => $record->estado === EstadoPlan::PorVerificar && $puede())
                ->schema([Textarea::make('motivo')->label('Por qué no se acepta')->required()->rows(2)])
                ->action(function (PlanAccion $record, array $data, Action $action): void {
                    ErroresDeNegocio::intentar($action, fn () => GestionarPlanAccion::rechazar($record, $data['motivo']));
                }),
            Action::make('cerrar')
                ->label('Verificar y cerrar')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (PlanAccion $record) => $record->pendiente() && $puede())
                ->schema([Textarea::make('observacion')->label('Observación de cierre')->rows(2)
                    ->helperText('Obligatoria si el plan no tiene evidencias ni mantenimientos.')])
                ->action(function (PlanAccion $record, array $data, Action $action): void {
                    ErroresDeNegocio::intentar($action, fn () => GestionarPlanAccion::cerrar($record, $data['observacion'] ?? null, auth()->user()));
                    Notification::make()->success()->title("Plan {$record->numero_formateado} cerrado")->send();
                }),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarPlanesAccion::route('/'),
            'view' => VerPlanAccion::route('/{record}'),
        ];
    }
}
