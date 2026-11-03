<?php

namespace App\Filament\App\Resources\ProgramasMantenimiento\RelationManagers;

use App\Actions\Mantenimiento\ProgramaAnual;
use App\Enums\EstadoActividad;
use App\Enums\EstadoPrograma;
use App\Filament\App\Resources\Mantenimientos\MantenimientoResource;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\ActividadMantenimiento;
use App\Models\Vehiculo;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Actividades del programa: programadas, próximas, vencidas, ejecutadas y canceladas, con su
 * historial (trazabilidad) y las acciones de reprogramar, cancelar y registrar la ejecución.
 */
class ActividadesRelationManager extends RelationManager
{
    protected static string $relationship = 'actividades';

    protected static ?string $title = 'Actividades';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-list-bullet';

    public const ETIQUETAS = [
        'programada' => 'Programada', 'proxima' => 'Próxima', 'vencida' => 'Vencida', 'ejecutada' => 'Ejecutada', 'cancelada' => 'Cancelada',
    ];

    public const COLORES = [
        'programada' => 'info', 'proxima' => 'warning', 'vencida' => 'danger', 'ejecutada' => 'success', 'cancelada' => 'gray',
    ];

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $editable = fn () => $this->getOwnerRecord()->estado !== EstadoPrograma::Reemplazado && auth()->user()?->can('editar_programas_mantenimiento');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehiculo', 'mantenimiento']))
            ->defaultSort(fn (Builder $query) => $query->orderBy('fecha_programada')->orderBy('vehiculo_id'))
            ->columns([
                TextColumn::make('fecha_programada')->label('Fecha')->date('d/m/Y')->sortable()
                    ->description(fn (ActividadMantenimiento $r) => $r->reprogramada() ? 'Original: ' . $r->fecha_original->format('d/m/Y') : null),
                TextColumn::make('vehiculo.placa')->label('Vehículo')->weight('bold')->searchable(),
                TextColumn::make('rutina_nombre')->label('Rutina')->wrap()->searchable()
                    ->description(fn (ActividadMantenimiento $r) => $r->frecuencia . ($r->incorporada ? ' · incorporada' : '')),
                TextColumn::make('km_programado')->label('Km prog.')->numeric(thousandsSeparator: '.')->placeholder('—'),
                TextColumn::make('situacion')->label('Estado')->badge()
                    ->state(fn (ActividadMantenimiento $r) => self::ETIQUETAS[$r->situacion()])
                    ->color(fn (ActividadMantenimiento $r) => self::COLORES[$r->situacion()]),
                TextColumn::make('fecha_ejecucion')->label('Ejecutada')->date('d/m/Y')->placeholder('—')
                    ->description(fn (ActividadMantenimiento $r) => match (true) {
                        $r->desviacionDias() === null => $r->motivo,
                        $r->desviacionDias() > 0 => $r->desviacionDias() . ' días tarde',
                        $r->desviacionDias() < 0 => abs($r->desviacionDias()) . ' días antes',
                        default => 'A tiempo',
                    }),
            ])
            ->filters([
                SelectFilter::make('situacion')->label('Estado')
                    ->options(self::ETIQUETAS)
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'vencida' => $query->pendientes()->whereDate('fecha_programada', '<', today()),
                        'proxima' => $query->pendientes()->whereDate('fecha_programada', '>=', today())->whereDate('fecha_programada', '<=', today()->addDays(15)),
                        'programada' => $query->pendientes()->whereDate('fecha_programada', '>', today()->addDays(15)),
                        'ejecutada', 'cancelada' => $query->where('estado', $data['value']),
                        default => $query,
                    }),
                SelectFilter::make('vehiculo_id')->label('Vehículo')->searchable()
                    ->options(fn () => Vehiculo::query()->whereIn('id', $this->getOwnerRecord()->actividades()->select('vehiculo_id'))->orderBy('placa')->pluck('placa', 'id')),
                SelectFilter::make('mes')->label('Mes')
                    ->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => ucfirst(\Illuminate\Support\Carbon::create(null, $m, 1)->translatedFormat('F'))]))
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->whereMonth('fecha_programada', $data['value']) : $query),
            ])
            ->recordActions([
                Action::make('ejecutar')
                    ->label('Registrar')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->visible(fn (ActividadMantenimiento $r) => $r->estado === EstadoActividad::Programada && auth()->user()?->can('crear_mantenimientos'))
                    ->url(fn (ActividadMantenimiento $r) => MantenimientoResource::getUrl('create', ['vehiculo' => $r->vehiculo_id, 'rutina' => $r->rutina_mantenimiento_id])),
                Action::make('reprogramar')
                    ->icon('heroicon-o-calendar')
                    ->color('gray')
                    ->visible(fn (ActividadMantenimiento $r) => $r->estado === EstadoActividad::Programada && $editable())
                    ->schema([
                        DatePicker::make('fecha')->label('Nueva fecha')->required(),
                        Textarea::make('motivo')->required()->rows(2),
                    ])
                    ->fillForm(fn (ActividadMantenimiento $r) => ['fecha' => $r->fecha_programada])
                    ->action(function (ActividadMantenimiento $record, array $data, Action $action): void {
                        ErroresDeNegocio::intentar($action, fn () => ProgramaAnual::reprogramar($record, $data['fecha'], $data['motivo'], auth()->user()));
                    }),
                Action::make('cancelar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ActividadMantenimiento $r) => $r->estado === EstadoActividad::Programada && $editable())
                    ->schema([Textarea::make('motivo')->required()->rows(2)->helperText('Ej.: vehículo desvinculado, rutina cambiada en el plan.')])
                    ->action(function (ActividadMantenimiento $record, array $data, Action $action): void {
                        ErroresDeNegocio::intentar($action, fn () => ProgramaAnual::cancelar($record, $data['motivo'], auth()->user()));
                    }),
                Action::make('historial')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Historial')
                    ->modalHeading(fn (ActividadMantenimiento $r) => "{$r->vehiculo?->placa} · {$r->rutina_nombre}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (ActividadMantenimiento $r) => new HtmlString(view('filament.mantenimiento.historial-actividad', ['a' => $r])->render())),
            ]);
    }
}
