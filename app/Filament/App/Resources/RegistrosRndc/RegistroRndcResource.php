<?php

namespace App\Filament\App\Resources\RegistrosRndc;

use App\Filament\App\Resources\RegistrosRndc\Pages\ListarRegistrosRndc;
use App\Models\RegistroRndc;
use App\Support\Carga\CatalogoRndc;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Bitácora de envíos al RNDC: qué se envió, el radicado o los errores, quién y cuándo. */
class RegistroRndcResource extends Resource
{
    protected static ?string $model = RegistroRndc::class;

    protected static ?string $slug = 'bitacora-rndc';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Carga';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Bitácora RNDC';

    protected static ?string $modelLabel = 'registro en el RNDC';

    protected static ?string $pluralModelLabel = 'bitácora del RNDC';

    protected static ?string $tenantRelationshipName = 'registrosRndc';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['usuario', 'registrable']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i a')->timezone(config('app.timezone')),
                TextColumn::make('proceso')->badge()->color('gray')->formatStateUsing(fn (int $state) => CatalogoRndc::PROCESOS[$state] ?? "Proceso {$state}"),
                TextColumn::make('registrable')->label('Registro')->state(fn (RegistroRndc $r) => $r->registrable?->etiquetaRndc() ?? '(eliminado)')->wrap(),
                TextColumn::make('estado')->label('Resultado')->badge()
                    ->formatStateUsing(fn (string $state, RegistroRndc $r) => $r->etiquetaEstado())
                    ->color(fn (string $state) => $state === 'error' ? 'danger' : 'success')
                    ->description(fn (RegistroRndc $r) => $r->errores ? Str::limit(collect($r->errores)->pluck('mensaje')->implode(' '), 120) : null),
                TextColumn::make('ingresoid')->label('Radicado')->placeholder('—')->copyable(),
                TextColumn::make('usuario.name')->label('Usuario')->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('estado')->options(['registrado' => 'Registrado', 'manual' => 'Registrado en la web', 'error' => 'Con errores']),
                SelectFilter::make('proceso')->options(CatalogoRndc::PROCESOS),
            ])
            ->recordActions([
                Action::make('detalle')->iconButton()->label('Ver')->icon('heroicon-o-eye')->color('gray')
                    ->modalHeading('Registro en el RNDC')
                    ->modalContent(fn (RegistroRndc $record) => view('filament.app.carga.detalle-registro-rndc', ['r' => $record]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Cerrar'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListarRegistrosRndc::route('/')];
    }
}
