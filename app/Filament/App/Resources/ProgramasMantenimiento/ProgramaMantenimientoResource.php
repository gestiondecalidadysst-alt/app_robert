<?php

namespace App\Filament\App\Resources\ProgramasMantenimiento;

use App\Actions\Mantenimiento\ProgramaAnual;
use App\Enums\EstadoActividad;
use App\Enums\EstadoPrograma;
use App\Filament\App\Resources\ProgramasMantenimiento\Pages\ListarProgramas;
use App\Filament\App\Resources\ProgramasMantenimiento\Pages\VerPrograma;
use App\Filament\App\Resources\ProgramasMantenimiento\RelationManagers\ActividadesRelationManager;
use App\Models\ProgramaMantenimiento;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Programa anual de mantenimiento preventivo (PESV): borrador → aprobado, con actividades programadas
 * por vehículo, su ejecución y su trazabilidad. Ver App\Actions\Mantenimiento\ProgramaAnual.
 */
class ProgramaMantenimientoResource extends Resource
{
    protected static ?string $model = ProgramaMantenimiento::class;

    protected static ?string $slug = 'programa-mantenimiento';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 25;

    protected static ?string $navigationLabel = 'Programa anual';

    protected static ?string $breadcrumb = 'Programa anual';

    protected static ?string $modelLabel = 'programa de mantenimiento';

    protected static ?string $pluralModelLabel = 'programas de mantenimiento';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['elaboradoPor', 'aprobadoPor'])
                ->withCount([
                    'actividades as programadas_count' => fn ($q) => $q->where('estado', '!=', EstadoActividad::Cancelada->value),
                    'actividades as ejecutadas_count' => fn ($q) => $q->where('estado', EstadoActividad::Ejecutada->value),
                ]))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('anio')->orderByDesc('version'))
            ->columns([
                TextColumn::make('anio')->label('Año')->weight('bold'),
                TextColumn::make('version')->label('Versión')->formatStateUsing(fn ($state) => "v{$state}"),
                TextColumn::make('estado')->badge(),
                TextColumn::make('programadas_count')->label('Programadas'),
                TextColumn::make('ejecutadas_count')->label('Ejecutadas'),
                TextColumn::make('cumplimiento')->label('Cumplimiento a la fecha')
                    ->state(function (ProgramaMantenimiento $r) {
                        $ind = ProgramaAnual::indicadores($r);
                        $exigibles = $ind->sum('exigibles');

                        return $exigibles ? round($ind->sum('ejecutadas') * 100 / $exigibles, 1).' %' : '—';
                    }),
                TextColumn::make('elaboradoPor.name')->label('Elaboró')->placeholder('—'),
                TextColumn::make('aprobadoPor.name')->label('Aprobó')->placeholder('—')
                    ->description(fn (ProgramaMantenimiento $r) => $r->aprobado_at?->format('d/m/Y')),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 6])
                ->schema([
                    TextEntry::make('estado')->badge(),
                    TextEntry::make('codigo_formato')->label('Código del formato')->placeholder('—'),
                    TextEntry::make('km_diarios_defecto')->label('Km/día por defecto')
                        ->helperText('Se usa si el vehículo no tiene lecturas suficientes.'),
                    TextEntry::make('elaborado')->label('Elaboró')
                        ->state(fn (ProgramaMantenimiento $r) => $r->elaboradoPor?->name ?? '—')
                        ->helperText(fn (ProgramaMantenimiento $r) => $r->elaborado_at?->format('d/m/Y h:i a')),
                    TextEntry::make('aprobado')->label('Aprobó')
                        ->state(fn (ProgramaMantenimiento $r) => $r->aprobadoPor?->name ?? ($r->estado === EstadoPrograma::Borrador ? 'Pendiente' : '—'))
                        ->helperText(fn (ProgramaMantenimiento $r) => $r->aprobado_at?->format('d/m/Y h:i a')),
                    TextEntry::make('observaciones')->placeholder('—'),
                ]),
            Section::make('Cumplimiento por trimestre')
                ->description('Indicador de la Supertransporte: actividades de mantenimiento preventivo programadas vs. ejecutadas.')
                ->columnSpanFull()
                ->schema([ViewEntry::make('indicadores')->hiddenLabel()->view('filament.mantenimiento.indicadores-programa')]),
        ]);
    }

    public static function getRelations(): array
    {
        return [ActividadesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarProgramas::route('/'),
            'view' => VerPrograma::route('/{record}'),
        ];
    }
}
