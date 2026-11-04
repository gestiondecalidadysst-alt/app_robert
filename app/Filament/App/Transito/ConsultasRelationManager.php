<?php

namespace App\Filament\App\Transito;

use App\Enums\TipoConsultaTransito;
use App\Models\ConsultaTransito;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Historial de consultas de tránsito de un conductor o vehículo, con lo que costó cada una. */
class ConsultasRelationManager extends RelationManager
{
    protected static string $relationship = 'consultasTransito';

    protected static ?string $title = 'Historial de consultas';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-receipt-refund';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ComparendosRelationManager::canViewForRecord($ownerRecord, $pageClass);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($q) => $q->with('usuario'))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Sin consultas')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i a')->timezone(config('app.timezone')),
                TextColumn::make('tipo')->badge()->formatStateUsing(fn (TipoConsultaTransito $state) => $state->getLabel()),
                TextColumn::make('origen')->badge()->color('gray'),
                TextColumn::make('estado')->label('Resultado')->badge()
                    ->formatStateUsing(fn ($state, ConsultaTransito $r) => $r->etiquetaEstado())
                    ->color(fn ($state) => match ($state) { 'ok' => 'success', 'sin_datos' => 'gray', default => 'danger' })
                    ->description(fn (ConsultaTransito $r) => $r->error ? \Illuminate\Support\Str::limit($r->error, 80) : null),
                IconColumn::make('cobrada')->boolean(),
                TextColumn::make('precio')->label('Valor')->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.'))->alignEnd(),
                TextColumn::make('usuario.name')->label('Usuario')->placeholder('Automática')->visibleFrom('md'),
            ]);
    }
}
