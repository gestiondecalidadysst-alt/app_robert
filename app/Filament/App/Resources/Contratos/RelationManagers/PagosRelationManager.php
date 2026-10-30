<?php

namespace App\Filament\App\Resources\Contratos\RelationManagers;

use App\Actions\Operacion\RegistrarPago;
use App\Enums\MetodoPago;
use App\Filament\Compartido\ErroresDeNegocio;
use App\Models\Contrato;
use App\Models\PagoContrato;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Abonos del contrato. Saldo = valor − pagos.
 */
class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';

    protected static ?string $title = 'Pagos';

    protected static ?string $modelLabel = 'pago';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-banknotes';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', PagoContrato::class);
    }

    public function table(Table $table): Table
    {
        $pesos = fn ($state) => '$ ' . number_format((float) $state, 0, ',', '.');

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('usuario'))
            ->defaultSort(fn ($query) => $query->orderByDesc('fecha')->orderByDesc('id'))
            ->emptyStateHeading('Sin pagos')
            ->columns([
                TextColumn::make('fecha')->date('d/m/Y')->weight('bold'),
                TextColumn::make('valor')->formatStateUsing($pesos)->alignEnd(),
                TextColumn::make('metodo')->label('Método')->badge()->color('gray'),
                TextColumn::make('referencia')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('observacion')->label('Observación')->placeholder('—')->limit(60)->visibleFrom('lg'),
                TextColumn::make('usuario.name')->label('Registró')->placeholder('—')->visibleFrom('lg'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar pago')
                    ->modalHeading(fn () => 'Registrar pago · saldo ' . $pesos($this->getOwnerRecord()->saldo()))
                    ->visible(fn () => ! $this->getOwnerRecord()->anulado() && $this->getOwnerRecord()->saldo() > 0)
                    ->schema([
                        DatePicker::make('fecha')->default(now())->maxDate(now())->required(),
                        TextInput::make('valor')->numeric()->prefix('$')->minValue(1)->required()
                            ->default(fn () => $this->getOwnerRecord()->saldo()),
                        Select::make('metodo')->label('Método')->options(MetodoPago::class)->default(MetodoPago::Transferencia->value)->required(),
                        TextInput::make('referencia')->maxLength(255),
                        Textarea::make('observacion')->label('Observación')->rows(2),
                    ])
                    ->using(fn (array $data, CreateAction $action) => ErroresDeNegocio::intentar(
                        $action,
                        fn () => app(RegistrarPago::class)($this->getOwnerRecord(), $data),
                    ))
                    ->after(fn () => $this->dispatch('contrato-actualizado')),
            ])
            ->recordActions([
                DeleteAction::make()->after(fn () => $this->dispatch('contrato-actualizado')),
            ]);
    }
}
