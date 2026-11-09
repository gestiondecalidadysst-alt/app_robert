<?php

namespace App\Filament\App\Resources\TarifasSicetac;

use App\Filament\App\Resources\TarifasSicetac\Pages\ListarTarifasSicetac;
use App\Models\Municipio;
use App\Models\TarifaSicetac;
use App\Support\Carga\CatalogoRndc;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Costos mínimos del SICE-TAC por ruta.
 *
 * El Ministerio no ofrece un servicio de consulta, así que la empresa carga aquí las
 * tablas que publica. De aquí sale el mínimo que se propone y se exige en el manifiesto.
 */
class TarifaSicetacResource extends Resource
{
    protected static ?string $model = TarifaSicetac::class;

    protected static ?string $slug = 'tarifas-sicetac';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Carga';

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'SICE-TAC';

    protected static ?string $modelLabel = 'costo mínimo';

    protected static ?string $pluralModelLabel = 'costos mínimos SICE-TAC';

    protected static ?string $tenantRelationshipName = 'tarifasSicetac';

    public const RECURSO = 'tarifas_sicetac';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('origen_id')->label('Municipio de origen')->required()->searchable()->preload(false)
                ->relationship('origen', 'nombre', fn ($query) => $query->orderBy('nombre'))
                ->getOptionLabelFromRecordUsing(fn (Municipio $m) => $m->nombre_completo),
            Select::make('destino_id')->label('Municipio de destino')->required()->searchable()->preload(false)
                ->relationship('destino', 'nombre', fn ($query) => $query->orderBy('nombre'))
                ->getOptionLabelFromRecordUsing(fn (Municipio $m) => $m->nombre_completo),
            Select::make('configuracion')->label('Configuración vehicular')->native(false)
                ->options(CatalogoRndc::CONFIGURACIONES_VEHICULO)
                ->placeholder('Cualquiera')
                ->helperText('Déjelo vacío si el mismo costo aplica a cualquier configuración.'),
            TextInput::make('valor_minimo')->label('Costo mínimo del viaje')->required()
                ->numeric()->minValue(1)->prefix('$')->inputMode('numeric'),
            DatePicker::make('vigente_desde')->label('Vigente desde')->required()->native(false)->default(today()),
            DatePicker::make('vigente_hasta')->label('Vigente hasta')->native(false)
                ->helperText('Vacío = sigue vigente hasta que el Ministerio publique otra tabla.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['origen', 'destino']))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Cargue los costos mínimos del SICE-TAC')
            ->emptyStateDescription('Sin tarifas, el flete mínimo queda sin validar aquí y solo lo revisa el RNDC al radicar.')
            ->columns([
                TextColumn::make('ruta')->label('Ruta')->searchable(false)
                    ->state(fn (TarifaSicetac $t) => ($t->origen?->nombre ?? '—').' → '.($t->destino?->nombre ?? '—')),
                TextColumn::make('configuracion')->label('Configuración')
                    ->formatStateUsing(fn ($state) => $state ? (CatalogoRndc::CONFIGURACIONES_VEHICULO[$state] ?? $state) : 'Cualquiera')
                    ->badge()->color('gray'),
                TextColumn::make('valor_minimo')->label('Costo mínimo')->numeric(thousandsSeparator: '.')->prefix('$ ')->alignEnd(),
                TextColumn::make('vigente_desde')->label('Desde')->date('d/m/Y'),
                TextColumn::make('vigente_hasta')->label('Hasta')->date('d/m/Y')->placeholder('Vigente'),
            ])
            ->headerActions([
                CreateAction::make()->label('Nuevo costo mínimo'),
                ImportarSicetac::accion(),
            ])
            ->recordActions([EditAction::make()->iconButton(), DeleteAction::make()->iconButton()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListarTarifasSicetac::route('/')];
    }
}
