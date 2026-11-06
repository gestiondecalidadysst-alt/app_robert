<?php

namespace App\Filament\App\Resources\Conductores;

use App\Enums\EstadoConductor;
use App\Enums\TipoDocumentoConductor;
use App\Filament\App\Acciones\HojaVidaPdf;
use App\Filament\App\Carga\AccionesRndc;
use App\Filament\App\Resources\Conductores\Acciones\CambiarEstadoConductorAction;
use App\Filament\App\Resources\Conductores\Pages\CrearConductor;
use App\Filament\App\Resources\Conductores\Pages\EditarConductor;
use App\Filament\App\Resources\Conductores\Pages\ListarConductores;
use App\Filament\App\Resources\Conductores\Pages\VerConductor;
use App\Filament\App\Resources\Conductores\RelationManagers\DocumentosRelationManager;
use App\Filament\App\Resources\Conductores\RelationManagers\NovedadesRelationManager;
use App\Filament\App\Resources\Conductores\RelationManagers\VehiculosRelationManager;
use App\Filament\App\Resources\Conductores\Schemas\ConductorForm;
use App\Filament\App\Resources\Conductores\Schemas\ConductorInfolist;
use App\Filament\App\Transito\ComparendosRelationManager;
use App\Filament\App\Transito\ConsultasRelationManager;
use App\Models\Conductor;
use App\Models\DocumentoConductor;
use App\Models\Oficina;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Conductores (operadores). La hoja de vida reúne datos, documentos con historial,
 * vehículos asignados y novedades.
 */
class ConductorResource extends Resource
{
    protected static ?string $model = Conductor::class;

    protected static ?string $slug = 'conductores';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Flota y conductores';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'conductor';

    protected static ?string $pluralModelLabel = 'conductores';

    protected static ?string $recordTitleAttribute = 'nombres';

    protected static ?string $tenantRelationshipName = 'conductores';

    public static function getRecordTitle(?Model $record): string
    {
        return $record?->nombre_completo ?? 'Conductor';
    }

    public static function form(Schema $schema): Schema
    {
        return ConductorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConductorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'tipoIdentificacion', 'documentos.categoriaLicencia', 'asignacionesVigentes.vehiculo',
            ]))
            ->defaultSort('nombres')
            ->recordUrl(fn (Conductor $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                ImageColumn::make('foto_path')->label('')->disk('public')->circular()->imageSize(36)->visibleFrom('md'),
                TextColumn::make('nombres')
                    ->label('Conductor')
                    ->formatStateUsing(fn (Conductor $record) => $record->nombre_completo)
                    ->description(fn (Conductor $record) => trim(($record->tipoIdentificacion?->codigo ?? '').' '.$record->documento))
                    ->searchable(['nombres', 'apellidos', 'documento'])
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('telefono')->label('Celular')->visibleFrom('lg'),
                TextColumn::make('licencia')
                    ->label('Licencia')
                    ->state(fn (Conductor $record) => $record->licencia()?->categoriaLicencia?->codigo)
                    ->badge()
                    ->color(fn (Conductor $record) => $record->licencia()?->categoriaLicencia?->esServicioPublico() ? 'gray' : 'danger')
                    ->description(fn (Conductor $record) => $record->licencia() ? 'Vence '.$record->licencia()->fecha_vencimiento->format('d/m/Y') : null)
                    ->placeholder('Sin registrar'),
                TextColumn::make('vehiculos')
                    ->label('Vehículos')
                    ->state(fn (Conductor $record) => $record->asignacionesVigentes
                        ->map(fn ($a) => '<span class="ruta-placa">'.e($a->vehiculo?->placa).'</span>')
                        ->implode(' '))
                    ->html()
                    ->placeholder('—')
                    ->visibleFrom('lg'),
                TextColumn::make('estado')->badge()->sortable(),
                AccionesRndc::columnaEstado()->toggleable(),
                TextColumn::make('documentos_estado')
                    ->label('Documentos')
                    ->badge()
                    ->state(fn (Conductor $record) => $record->estadoDocumental())
                    ->description(fn (Conductor $record) => $record->resumenDocumentos()),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(EstadoConductor::class)
                    ->multiple()
                    ->default([EstadoConductor::Activo->value, EstadoConductor::Suspendido->value]),
                SelectFilter::make('documentos')
                    ->label('Documentos')
                    ->options([
                        'con_problemas' => 'Vencidos o sin registrar',
                        'por_vencer' => 'Por vencer',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'con_problemas' => self::conDocumentosVencidosOFaltantes($query),
                        'por_vencer' => $query->whereHas('documentos', fn (Builder $d) => $d->vigentes()
                            ->whereIn('tipo', array_map(fn ($t) => $t->value, Conductor::tiposObligatorios()))
                            ->porVencer()),
                        default => $query,
                    }),
                TernaryFilter::make('con_vehiculo')
                    ->label('Vehículo asignado')
                    ->trueLabel('Con vehículo')
                    ->falseLabel('Sin vehículo')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('asignacionesVigentes'),
                        false: fn (Builder $q) => $q->whereDoesntHave('asignacionesVigentes'),
                        blank: fn (Builder $q) => $q,
                    ),
                SelectFilter::make('oficina_id')
                    ->label('Oficina')
                    ->options(fn () => Oficina::query()->orderBy('nombre')->pluck('nombre', 'id')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Hoja de vida'),
                    HojaVidaPdf::conductor(),
                    EditAction::make(),
                    CambiarEstadoConductorAction::make(),
                    DeleteAction::make()
                        ->modalDescription('Se elimina el conductor con sus documentos. Si dejó la empresa, use «Cambiar estado → Retiro» para conservar el historial.'),
                ]),
            ]);
    }

    /** Conductores con algún documento obligatorio vencido o sin registrar. */
    public static function conDocumentosVencidosOFaltantes(Builder $query): Builder
    {
        $tipos = array_map(fn (TipoDocumentoConductor $t) => $t->value, Conductor::tiposObligatorios());
        $tabla = (new DocumentoConductor)->getTable();
        $conductores = $query->getModel()->getTable();

        return $query->where(fn (Builder $q) => $q
            ->whereHas('documentos', fn (Builder $d) => $d->vigentes()->whereIn('tipo', $tipos)->whereDate('fecha_vencimiento', '<', today()))
            ->orWhereRaw(
                "(select count(distinct tipo) from {$tabla} where {$tabla}.conductor_id = {$conductores}.id and {$tabla}.deleted_at is null and {$tabla}.tipo in (".implode(',', array_fill(0, count($tipos), '?')).')) < ?',
                [...$tipos, count($tipos)],
            ));
    }

    public static function getRelations(): array
    {
        return [
            DocumentosRelationManager::class,
            VehiculosRelationManager::class,
            NovedadesRelationManager::class,
            ComparendosRelationManager::class,
            ConsultasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarConductores::route('/'),
            'create' => CrearConductor::route('/crear'),
            'view' => VerConductor::route('/{record}'),
            'edit' => EditarConductor::route('/{record}/editar'),
        ];
    }
}
