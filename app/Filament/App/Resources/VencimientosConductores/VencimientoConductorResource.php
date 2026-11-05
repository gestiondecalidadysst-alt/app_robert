<?php

namespace App\Filament\App\Resources\VencimientosConductores;

use App\Enums\EstadoDocumento;
use App\Enums\TipoDocumentoConductor;
use App\Filament\App\Resources\Conductores\ConductorResource;
use App\Filament\App\Resources\Conductores\Schemas\DocumentoConductorForm;
use App\Filament\App\Resources\Vencimientos\VencimientoResource;
use App\Filament\App\Resources\VencimientosConductores\Pages\ListarVencimientosConductores;
use App\Models\Conductor;
use App\Models\DocumentoConductor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Vencimientos de documentos de conductores (licencias, seguridad social…): solo el vigente de
 * cada tipo por conductor no retirado, ordenado por vencimiento.
 */
class VencimientoConductorResource extends Resource
{
    protected static ?string $model = DocumentoConductor::class;

    protected static ?string $slug = 'vencimientos-conductores';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Flota y conductores';

    protected static ?int $navigationSort = 41;

    protected static ?string $navigationLabel = 'Vencimientos';

    protected static ?string $breadcrumb = 'Vencimientos de conductores';

    protected static ?string $modelLabel = 'vencimiento';

    protected static ?string $pluralModelLabel = 'vencimientos de conductores';

    protected static ?string $tenantRelationshipName = 'documentosConductor';

    protected static bool $isGloballySearchable = false;

    /** En el menú va dentro de «Vencimientos» (pestaña Conductores); solo aparece sola si no se ven los de vehículos. */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny() && ! VencimientoResource::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        $total = self::consultaBase()->vencidos()->count();

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Documentos obligatorios vencidos';
    }

    /** Documentos obligatorios vigentes de conductores no retirados (para contadores). */
    public static function consultaBase(): Builder
    {
        return DocumentoConductor::query()
            ->vigentes()
            ->whereIn('tipo', array_map(fn ($t) => $t->value, Conductor::tiposObligatorios()))
            ->whereHas('conductor', fn (Builder $q) => $q->vinculados());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->vigentes()
            ->whereHas('conductor', fn (Builder $q) => $q->vinculados());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['conductor', 'categoriaLicencia']))
            ->defaultSort('fecha_vencimiento')
            ->recordUrl(fn (DocumentoConductor $r) => ConductorResource::getUrl('view', ['record' => $r->conductor_id]))
            ->columns([
                TextColumn::make('conductor.nombres')
                    ->label('Conductor')
                    ->formatStateUsing(fn (DocumentoConductor $r) => $r->conductor?->nombre_completo)
                    ->description(fn (DocumentoConductor $r) => $r->conductor?->telefono)
                    ->searchable(['nombres', 'apellidos', 'documento'])
                    ->weight('bold'),
                TextColumn::make('tipo')->label('Documento')->badge()->color('gray'),
                TextColumn::make('categoriaLicencia.codigo')->label('Cat.')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('fecha_vencimiento')->label('Vence')->date('d/m/Y')->sortable()->weight('bold'),
                TextColumn::make('dias')
                    ->label('Días')
                    ->state(fn (DocumentoConductor $r) => $r->diasParaVencer())
                    ->formatStateUsing(fn (int $state) => $state < 0 ? 'Hace '.abs($state) : ($state === 0 ? 'Hoy' : "En {$state}"))
                    ->color(fn (DocumentoConductor $r) => $r->estado()->getColor()),
                TextColumn::make('estado')->label('Estado')->badge()->state(fn (DocumentoConductor $r) => $r->estado()),
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
                SelectFilter::make('tipo')->label('Documento')->options(TipoDocumentoConductor::class)->multiple(),
            ])
            ->recordActions([
                Action::make('renovar')
                    ->label('Renovar')
                    ->icon('heroicon-o-arrow-path')
                    ->authorize(fn () => auth()->user()->can('create', DocumentoConductor::class))
                    ->modalHeading(fn (DocumentoConductor $r) => "Renovar {$r->tipo->getLabel()} · {$r->conductor->nombre_completo}")
                    ->schema(DocumentoConductorForm::campos(tipoFijo: true))
                    ->fillForm(fn (DocumentoConductor $r) => [
                        'tipo' => $r->tipo->value,
                        'categoria_licencia_id' => $r->categoria_licencia_id,
                        'entidad' => $r->entidad,
                    ])
                    ->action(fn (DocumentoConductor $r, array $data) => $r->conductor->documentos()->create([...$data, 'tipo' => $r->tipo]))
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
            'index' => ListarVencimientosConductores::route('/'),
        ];
    }
}
