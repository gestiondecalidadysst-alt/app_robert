<?php

namespace App\Filament\App\Resources\Vehiculos;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoDocumentoVehiculo;
use App\Filament\App\Carga\AccionesRndc;
use App\Filament\App\Resources\Vehiculos\Acciones\CambiarEstadoAction;
use App\Filament\App\Resources\Vehiculos\Pages\CrearVehiculo;
use App\Filament\App\Resources\Vehiculos\Pages\EditarVehiculo;
use App\Filament\App\Resources\Vehiculos\Pages\ListarVehiculos;
use App\Filament\App\Resources\Vehiculos\Pages\PlanMantenimientoVehiculo;
use App\Filament\App\Resources\Vehiculos\Pages\VerVehiculo;
use App\Filament\App\Resources\Vehiculos\RelationManagers\ConductoresRelationManager;
use App\Filament\App\Resources\Vehiculos\RelationManagers\DocumentosRelationManager;
use App\Filament\App\Resources\Vehiculos\RelationManagers\MantenimientosRelationManager;
use App\Filament\App\Resources\Vehiculos\RelationManagers\NovedadesRelationManager;
use App\Filament\App\Resources\Vehiculos\RelationManagers\PlanesAccionRelationManager;
use App\Filament\App\Resources\Vehiculos\Schemas\VehiculoForm;
use App\Filament\App\Resources\Vehiculos\Schemas\VehiculoInfolist;
use App\Filament\App\Transito\ComparendosRelationManager;
use App\Filament\App\Transito\ConsultasRelationManager;
use App\Models\ClaseVehiculo;
use App\Models\DocumentoVehiculo;
use App\Models\Propietario;
use App\Models\Vehiculo;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Vehículos de la flota. El detalle (hoja de vida) reúne datos, documentos con historial y novedades.
 */
class VehiculoResource extends Resource
{
    protected static ?string $model = Vehiculo::class;

    protected static ?string $slug = 'vehiculos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Flota y conductores';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'vehículo';

    protected static ?string $pluralModelLabel = 'vehículos';

    protected static ?string $recordTitleAttribute = 'placa';

    protected static ?string $tenantRelationshipName = 'vehiculos';

    public static function form(Schema $schema): Schema
    {
        return VehiculoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VehiculoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'marca', 'linea', 'claseVehiculo', 'propietario', 'documentos',
            ]))
            ->defaultSort('placa')
            ->recordUrl(fn (Vehiculo $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                ImageColumn::make('foto_path')->label('')->disk('public')->imageSize(40)->square()->visibleFrom('lg'),
                TextColumn::make('placa')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state) => new HtmlString('<span class="ruta-placa">'.e($state).'</span>'))
                    ->description(fn (Vehiculo $record) => $record->numero_interno ? "Interno {$record->numero_interno}" : null),
                TextColumn::make('numero_interno')->label('Interno')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('descripcion')
                    ->label('Vehículo')
                    ->description(fn (Vehiculo $record) => $record->claseVehiculo?->nombre)
                    ->visibleFrom('md'),
                TextColumn::make('capacidad_pasajeros')->label('Pasajeros')->alignCenter()->toggleable()->visibleFrom('lg'),
                TextColumn::make('propietario.nombre_completo')
                    ->label('Propietario')
                    ->searchable(['nombres', 'apellidos', 'documento'])
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('estado')->badge()->sortable(),
                AccionesRndc::columnaEstado()->toggleable(),
                TextColumn::make('documentos_estado')
                    ->label('Documentos')
                    ->badge()
                    ->state(fn (Vehiculo $record) => $record->estadoDocumental())
                    ->description(fn (Vehiculo $record) => $record->resumenDocumentos()),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(EstadoVehiculo::class)
                    ->multiple()
                    ->default([EstadoVehiculo::Operando->value, EstadoVehiculo::Suspendido->value, EstadoVehiculo::Taller->value]),
                SelectFilter::make('documentos')
                    ->label('Documentos')
                    ->options([
                        'con_problemas' => 'Vencidos o sin registrar',
                        'por_vencer' => 'Por vencer',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'con_problemas' => self::conDocumentosVencidosOFaltantes($query),
                        'por_vencer' => $query->whereHas('documentos', fn (Builder $d) => $d->vigentes()
                            ->porVencer()),
                        default => $query,
                    }),
                SelectFilter::make('clase_vehiculo_id')
                    ->label('Clase')
                    ->options(fn () => ClaseVehiculo::query()->orderBy('nombre')->pluck('nombre', 'id')),
                SelectFilter::make('propietario_id')
                    ->label('Propietario')
                    ->options(fn () => Propietario::query()->orderBy('nombres')->get()->pluck('nombre_completo', 'id'))
                    ->searchable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Hoja de vida'),
                    EditAction::make(),
                    CambiarEstadoAction::make(),
                    DeleteAction::make()
                        ->modalDescription('Se elimina el vehículo con sus documentos. Si salió de la flota, use «Cambiar estado → Desvinculación» para conservar el historial.'),
                ]),
            ]);
    }

    /** Vehículos con algún documento obligatorio vencido o sin registrar. */
    public static function conDocumentosVencidosOFaltantes(Builder $query): Builder
    {
        $tipos = array_map(fn (TipoDocumentoVehiculo $t) => $t->value, TipoDocumentoVehiculo::obligatorios());
        $tabla = (new DocumentoVehiculo)->getTable();
        $vehiculos = $query->getModel()->getTable();

        return $query->where(fn (Builder $q) => $q
            ->whereHas('documentos', fn (Builder $d) => $d->vigentes()->whereDate('fecha_vencimiento', '<', today()))
            ->orWhereRaw(
                "(select count(distinct tipo) from {$tabla} where {$tabla}.vehiculo_id = {$vehiculos}.id and {$tabla}.deleted_at is null and {$tabla}.tipo in (".implode(',', array_fill(0, count($tipos), '?')).')) < ?',
                [...$tipos, count($tipos)],
            ));
    }

    public static function getRelations(): array
    {
        return [
            DocumentosRelationManager::class,
            ConductoresRelationManager::class,
            NovedadesRelationManager::class,
            MantenimientosRelationManager::class,
            PlanesAccionRelationManager::class,
            ComparendosRelationManager::class,
            ConsultasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarVehiculos::route('/'),
            'create' => CrearVehiculo::route('/crear'),
            'view' => VerVehiculo::route('/{record}'),
            'edit' => EditarVehiculo::route('/{record}/editar'),
            'plan-mantenimiento' => PlanMantenimientoVehiculo::route('/{record}/plan-mantenimiento'),
        ];
    }
}
