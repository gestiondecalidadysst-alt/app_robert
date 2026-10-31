<?php

namespace App\Filament\App\Resources\Contratos;

use App\Enums\FormaPago;
use App\Enums\ObjetoContrato;
use App\Filament\App\Resources\Contratos\Pages\CrearContrato;
use App\Filament\App\Resources\Contratos\Pages\EditarContrato;
use App\Filament\App\Resources\Contratos\Pages\ListarContratos;
use App\Filament\App\Resources\Contratos\Pages\VerContrato;
use App\Filament\App\Resources\Contratos\RelationManagers\DocumentosRelationManager;
use App\Filament\App\Resources\Contratos\RelationManagers\FuecsRelationManager;
use App\Filament\App\Resources\Contratos\RelationManagers\PagosRelationManager;
use App\Filament\App\Resources\Contratos\Schemas\ContratoForm;
use App\Filament\App\Resources\Contratos\Schemas\ContratoInfolist;
use App\Models\Cliente;
use App\Models\Contrato;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Contratos de transporte especial con su saldo y sus FUEC.
 */
class ContratoResource extends Resource
{
    protected static ?string $model = Contrato::class;

    protected static ?string $slug = 'contratos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'contrato';

    protected static ?string $pluralModelLabel = 'contratos';

    protected static ?string $tenantRelationshipName = 'contratos';

    public static function getRecordTitle(?Model $record): string
    {
        return $record ? "Contrato {$record->numero_formateado}" : 'Contrato';
    }

    public static function form(Schema $schema): Schema
    {
        return ContratoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContratoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        $pesos = fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cliente.tipoIdentificacion', 'documentos'])->withSum('pagos', 'valor')->withCount(['fuecs' => fn ($q) => $q->noAnulados()]))
            ->defaultSort('numero', 'desc')
            ->recordUrl(fn (Contrato $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('numero')->label('No.')->sortable()->searchable()->weight('bold')
                    ->formatStateUsing(fn (Contrato $record) => $record->numero_formateado),
                TextColumn::make('cliente.nombre')->label('Contratante')->searchable(['nombre', 'documento'])
                    ->description(fn (Contrato $record) => $record->objeto->getLabel()),
                TextColumn::make('fecha_inicio')->label('Vigencia')->sortable()
                    ->formatStateUsing(fn (Contrato $record) => $record->vigencia)->visibleFrom('md'),
                TextColumn::make('valor')->formatStateUsing($pesos)->alignEnd()->sortable()->visibleFrom('lg'),
                TextColumn::make('saldo')->label('Saldo')->alignEnd()
                    ->state(fn (Contrato $record) => $record->saldo())
                    ->formatStateUsing($pesos)
                    ->color(fn (Contrato $record) => $record->saldo() > 0 ? 'danger' : 'success'),
                TextColumn::make('fuecs_count')->label('FUEC')->badge()->color('gray')->alignCenter()->visibleFrom('md'),
                TextColumn::make('estado')->badge()->state(fn (Contrato $record) => $record->estado()),
                TextColumn::make('firma')
                    ->label('Firma')
                    ->badge()
                    ->state(fn (Contrato $record) => $record->documentoActivo()?->estadoFirma())
                    ->placeholder('Sin emitir')
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options([
                        'vigente' => 'Vigentes',
                        'programado' => 'Programados',
                        'terminado' => 'Terminados',
                        'anulado' => 'Anulados',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'vigente' => $query->noAnulados()->whereDate('fecha_inicio', '<=', today())->whereDate('fecha_fin', '>=', today()),
                        'programado' => $query->noAnulados()->whereDate('fecha_inicio', '>', today()),
                        'terminado' => $query->noAnulados()->whereDate('fecha_fin', '<', today()),
                        'anulado' => $query->whereNotNull('anulado_at'),
                        default => $query,
                    }),
                TernaryFilter::make('con_saldo')
                    ->label('Saldo')
                    ->trueLabel('Con saldo pendiente')
                    ->falseLabel('Pagados')
                    ->queries(
                        true: fn (Builder $q) => $q->noAnulados()->whereRaw('valor > (select coalesce(sum(valor), 0) from pagos_contrato where pagos_contrato.contrato_id = contratos.id)'),
                        false: fn (Builder $q) => $q->whereRaw('valor <= (select coalesce(sum(valor), 0) from pagos_contrato where pagos_contrato.contrato_id = contratos.id)'),
                        blank: fn (Builder $q) => $q,
                    ),
                SelectFilter::make('objeto')->options(ObjetoContrato::class),
                SelectFilter::make('forma_pago')->label('Forma de pago')->options(FormaPago::class),
                SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->options(fn () => Cliente::query()->orderBy('nombre')->pluck('nombre', 'id'))
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                EditAction::make()->visible(fn (Contrato $record) => ! $record->anulado() && ! $record->documentoActivo()),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DocumentosRelationManager::class,
            FuecsRelationManager::class,
            PagosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarContratos::route('/'),
            'create' => CrearContrato::route('/crear'),
            'view' => VerContrato::route('/{record}'),
            'edit' => EditarContrato::route('/{record}/editar'),
        ];
    }
}
