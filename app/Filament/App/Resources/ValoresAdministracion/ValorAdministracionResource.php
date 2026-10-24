<?php

namespace App\Filament\App\Resources\ValoresAdministracion;

use App\Filament\App\Resources\ValoresAdministracion\Pages\ManageValoresAdministracion;
use App\Models\ClaseVehiculo;
use App\Models\ValorAdministracion;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class ValorAdministracionResource extends Resource
{
    protected static ?string $model = ValorAdministracion::class;

    protected static ?string $slug = 'valores-administracion';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'valor de administración';

    protected static ?string $pluralModelLabel = 'valores de administración';

    protected static ?string $navigationLabel = 'Valor de administración';

    protected static ?string $breadcrumb = 'Valor de administración';

    protected static ?string $tenantRelationshipName = 'valoresAdministracion';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('clase_vehiculo_id')
                    ->label('Clase de vehículo')
                    ->options(fn () => ClaseVehiculo::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('valor')
                    ->label('Valor mensual')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->required(),
                DatePicker::make('vigente_desde')
                    ->label('Vigente desde')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(now()->startOfMonth())
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                            ->where('empresa_id', Filament::getTenant()?->getKey())
                            ->where('clase_vehiculo_id', $get('clase_vehiculo_id')),
                    )
                    ->validationMessages(['unique' => 'Ya hay un valor para esta clase con la misma fecha.']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('claseVehiculo'))
            ->defaultSort('vigente_desde', 'desc')
            ->defaultGroup(Group::make('claseVehiculo.nombre')->label('Clase')->collapsible())
            ->columns([
                TextColumn::make('claseVehiculo.nombre')->label('Clase')->sortable(),
                TextColumn::make('valor')->label('Valor mensual')->money('COP', locale: 'es_CO')->sortable(),
                TextColumn::make('vigente_desde')->label('Vigente desde')->date('d/m/Y')->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (ValorAdministracion $record): string => $record->is(ValorAdministracion::vigente($record->empresa_id, $record->clase_vehiculo_id))
                        ? 'Vigente'
                        : ($record->vigente_desde->isFuture() ? 'Programado' : 'Histórico'))
                    ->color(fn (string $state): string => match ($state) {
                        'Vigente' => 'success',
                        'Programado' => 'info',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('clase_vehiculo_id')
                    ->label('Clase')
                    ->options(fn () => ClaseVehiculo::query()->orderBy('nombre')->pluck('nombre', 'id')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageValoresAdministracion::route('/'),
        ];
    }
}
