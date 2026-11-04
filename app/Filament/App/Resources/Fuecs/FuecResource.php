<?php

namespace App\Filament\App\Resources\Fuecs;

use App\Filament\App\Resources\Fuecs\Acciones\AccionesFuec;
use App\Filament\App\Resources\Fuecs\Pages\ExpedirFuec;
use App\Filament\App\Resources\Fuecs\Pages\ListarFuecs;
use App\Filament\App\Resources\Fuecs\Pages\VerFuec;
use App\Filament\App\Resources\Fuecs\Schemas\FuecForm;
use App\Filament\App\Resources\Fuecs\Schemas\FuecInfolist;
use App\Models\Fuec;
use App\Models\Vehiculo;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * FUEC (extractos de contrato). Se expiden con validación en el servidor; no se editan ni se borran: se anulan.
 */
class FuecResource extends Resource
{
    protected static ?string $model = Fuec::class;

    protected static ?string $slug = 'fuec';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'FUEC';

    protected static ?string $modelLabel = 'FUEC';

    protected static ?string $pluralModelLabel = 'FUEC';

    protected static ?string $tenantRelationshipName = 'fuecs';

    public static function getRecordTitle(?Model $record): string
    {
        return $record ? "FUEC {$record->datos['vehiculo']['placa']} · " . str_pad((string) $record->consecutivo, 4, '0', STR_PAD_LEFT) : 'FUEC';
    }

    public static function form(Schema $schema): Schema
    {
        return FuecForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FuecInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('contrato'))
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Fuec $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('numero')->label('FUEC No.')->fontFamily('mono')->searchable()
                    ->description(fn (Fuec $record) => 'Expedido ' . $record->created_at?->timezone('America/Bogota')->format('d/m/Y')),
                TextColumn::make('placa')->label('Vehículo')
                    ->formatStateUsing(fn (?string $state) => new HtmlString('<span class="ruta-placa">' . e($state) . '</span>'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('vehiculo', fn ($q) => $q->where('placa', 'like', '%' . strtoupper($search) . '%'))),
                TextColumn::make('conductor')->label('Conductor')
                    ->state(fn (Fuec $record) => $record->datos['conductores'][0]['nombre'] ?? '—')
                    ->description(fn (Fuec $record) => count($record->datos['conductores'] ?? []) > 1 ? '+' . (count($record->datos['conductores']) - 1) . ' más' : null)
                    ->visibleFrom('lg'),
                TextColumn::make('contrato.numero')->label('Contrato')
                    ->formatStateUsing(fn (Fuec $record) => $record->datos['contrato']['numero'])
                    ->description(fn (Fuec $record) => $record->datos['contrato']['contratante'])
                    ->visibleFrom('md'),
                TextColumn::make('fecha_inicio')->label('Vigencia')->sortable()
                    ->formatStateUsing(fn (Fuec $record) => $record->fecha_inicio->format('d/m/Y') . ' – ' . $record->fecha_fin->format('d/m/Y')),
                TextColumn::make('estado')->badge()->state(fn (Fuec $record) => $record->estado()),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options([
                        'vigente' => 'Vigentes hoy',
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
                SelectFilter::make('vehiculo_id')
                    ->label('Vehículo')
                    ->options(fn () => Vehiculo::query()->orderBy('placa')->pluck('placa', 'id'))
                    ->searchable(),
                Filter::make('expedidos')
                    ->label('Expedidos entre')
                    ->schema([
                        DatePicker::make('desde')->label('Expedidos desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['hasta'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    AccionesFuec::pdf(),
                    AccionesFuec::anular(),
                ]),
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarFuecs::route('/'),
            'create' => ExpedirFuec::route('/expedir'),
            'view' => VerFuec::route('/{record}'),
        ];
    }
}
