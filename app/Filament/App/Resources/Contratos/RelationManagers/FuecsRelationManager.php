<?php

namespace App\Filament\App\Resources\Contratos\RelationManagers;

use App\Filament\App\Resources\Fuecs\FuecResource;
use App\Models\Fuec;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * FUEC expedidos con este contrato.
 */
class FuecsRelationManager extends RelationManager
{
    protected static string $relationship = 'fuecs';

    protected static ?string $title = 'FUEC';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-check';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', Fuec::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('vehiculo'))
            ->defaultSort('consecutivo', 'desc')
            ->recordUrl(fn (Fuec $r) => FuecResource::getUrl('view', ['record' => $r]))
            ->emptyStateHeading('Sin FUEC expedidos')
            ->columns([
                TextColumn::make('consecutivo')->label('No.')->formatStateUsing(fn (int $state) => str_pad((string) $state, 4, '0', STR_PAD_LEFT)),
                TextColumn::make('placa')->label('Vehículo')
                    ->formatStateUsing(fn (?string $state) => new HtmlString('<span class="ruta-placa">'.e($state).'</span>')),
                TextColumn::make('conductor')->label('Conductor')->state(fn (Fuec $r) => $r->datos['conductores'][0]['nombre'] ?? '—')->visibleFrom('md'),
                TextColumn::make('fecha_inicio')->label('Vigencia')
                    ->formatStateUsing(fn (Fuec $r) => $r->fecha_inicio->format('d/m/Y').' – '.$r->fecha_fin->format('d/m/Y')),
                TextColumn::make('estado')->badge()->state(fn (Fuec $r) => $r->estado()),
            ])
            ->headerActions([
                Action::make('expedir')
                    ->label('Expedir FUEC')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => ! $this->getOwnerRecord()->anulado() && auth()->user()->can('create', Fuec::class))
                    ->url(fn () => FuecResource::getUrl('create', ['contrato' => $this->getOwnerRecord()->getKey()])),
            ]);
    }
}
