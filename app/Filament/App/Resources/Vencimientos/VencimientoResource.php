<?php

namespace App\Filament\App\Resources\Vencimientos;

use App\Enums\EstadoDocumento;
use App\Enums\TipoDocumentoVehiculo;
use App\Filament\App\Resources\Vehiculos\Schemas\DocumentoForm;
use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Filament\App\Resources\Vencimientos\Pages\ListarVencimientos;
use App\Filament\App\Resources\VencimientosConductores\VencimientoConductorResource;
use App\Models\DocumentoVehiculo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Pantalla operativa de vencimientos: solo el documento vigente de cada tipo por vehículo
 * de la flota activa, ordenado por fecha de vencimiento. Reemplaza "Control de documentos".
 */
class VencimientoResource extends Resource
{
    protected static ?string $model = DocumentoVehiculo::class;

    protected static ?string $slug = 'vencimientos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Flota y conductores';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Vencimientos';

    protected static ?string $modelLabel = 'vencimiento';

    protected static ?string $pluralModelLabel = 'vencimientos';

    protected static ?string $tenantRelationshipName = 'documentosVehiculo';

    protected static bool $isGloballySearchable = false;

    /** Un solo «Vencimientos» en el menú: suma vehículos y conductores (lo que el usuario puede ver). */
    public static function getNavigationBadge(): ?string
    {
        $total = self::consultaBase()->vencidos()->count()
            + (VencimientoConductorResource::canViewAny()
                ? VencimientoConductorResource::consultaBase()->vencidos()->count() : 0);

        return $total > 0 ? (string) $total : null;
    }

    /** Queda marcado también en la pestaña de conductores. */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [parent::getNavigationItemActiveRoutePattern(), VencimientoConductorResource::getNavigationItemActiveRoutePattern()];
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Documentos vencidos de vehículos y conductores';
    }

    /** @return list<Action> */
    public static function pestanas(string $actual): array
    {
        $c = VencimientoConductorResource::class;
        $cuenta = fn (bool $puede, $consulta) => $puede && ($n = $consulta->vencidos()->count()) ? $n : null;

        return [
            Action::make('pestana_vehiculos')->label('Vehículos')->icon('heroicon-o-truck')
                ->color($actual === 'vehiculos' ? 'primary' : 'gray')->outlined($actual !== 'vehiculos')
                ->badge($cuenta(static::canViewAny(), static::consultaBase()))->badgeColor('danger')
                ->visible(static::canViewAny())->url(static::getUrl()),
            Action::make('pestana_conductores')->label('Conductores')->icon('heroicon-o-users')
                ->color($actual === 'conductores' ? 'primary' : 'gray')->outlined($actual !== 'conductores')
                ->badge($cuenta($c::canViewAny(), $c::consultaBase()))->badgeColor('danger')
                ->visible($c::canViewAny())->url($c::getUrl()),
        ];
    }

    /** Documentos vigentes de vehículos en flota (sin desvinculados). */
    public static function consultaBase(): Builder
    {
        return DocumentoVehiculo::query()
            ->vigentes()
            ->whereHas('vehiculo', fn (Builder $q) => $q->enFlota());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->vigentes()
            ->whereHas('vehiculo', fn (Builder $q) => $q->enFlota());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehiculo', 'aseguradora']))
            ->defaultSort('fecha_vencimiento')
            ->recordUrl(fn (DocumentoVehiculo $r) => VehiculoResource::getUrl('view', ['record' => $r->vehiculo_id]))
            ->columns([
                TextColumn::make('vehiculo.placa')
                    ->label('Placa')
                    ->searchable()
                    ->formatStateUsing(fn (string $state) => new HtmlString('<span class="ruta-placa">'.e($state).'</span>'))
                    ->description(fn (DocumentoVehiculo $r) => $r->vehiculo?->numero_interno ? "Interno {$r->vehiculo->numero_interno}" : null),
                TextColumn::make('tipo')->label('Documento')->badge()->color('gray'),
                TextColumn::make('numero')->label('Número')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('emisor')->label('Expide')->state(fn (DocumentoVehiculo $r) => $r->emisor())->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('fecha_vencimiento')->label('Vence')->date('d/m/Y')->sortable()->weight('bold'),
                TextColumn::make('dias')
                    ->label('Días')
                    ->state(fn (DocumentoVehiculo $r) => $r->diasParaVencer())
                    ->formatStateUsing(fn (int $state) => $state < 0 ? 'Hace '.abs($state) : ($state === 0 ? 'Hoy' : "En {$state}"))
                    ->color(fn (DocumentoVehiculo $r) => $r->estado()->getColor()),
                TextColumn::make('estado')->label('Estado')->badge()->state(fn (DocumentoVehiculo $r) => $r->estado()),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        EstadoDocumento::Vencido->value => 'Vencidos',
                        EstadoDocumento::PorVencer->value => 'Por vencer',
                        'atencion' => 'Vencidos y por vencer',
                        EstadoDocumento::Vigente->value => 'Vigentes',
                    ])
                    ->default('atencion')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        EstadoDocumento::Vencido->value => $query->vencidos(),
                        EstadoDocumento::PorVencer->value => $query->porVencer(),
                        'atencion' => $query->enAtencion(),
                        EstadoDocumento::Vigente->value => $query->alDia(),
                        default => $query,
                    }),
                SelectFilter::make('tipo')->label('Documento')->options(TipoDocumentoVehiculo::class)->multiple(),
            ])
            ->recordActions([
                Action::make('renovar')->iconButton()
                    ->label('Renovar')
                    ->icon('heroicon-o-arrow-path')
                    ->authorize(fn () => auth()->user()->can('create', DocumentoVehiculo::class))
                    ->modalHeading(fn (DocumentoVehiculo $r) => "Renovar {$r->tipo->getLabel()} · {$r->vehiculo->placa}")
                    ->schema(DocumentoForm::campos(tipoFijo: true))
                    ->fillForm(fn (DocumentoVehiculo $r) => [
                        'tipo' => $r->tipo->value,
                        'aseguradora_id' => $r->aseguradora_id,
                        'entidad' => $r->entidad,
                    ])
                    ->action(fn (DocumentoVehiculo $r, array $data) => $r->vehiculo->documentos()->create([...$data, 'tipo' => $r->tipo]))
                    ->modalSubmitActionLabel('Guardar renovación')
                    ->successNotificationTitle('Documento renovado'),
            ])
            ->emptyStateHeading('Nada por vencer')
            ->emptyStateDescription('No hay documentos en este filtro. Cambie el filtro a «Vigentes» para ver el resto.');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarVencimientos::route('/'),
        ];
    }
}
