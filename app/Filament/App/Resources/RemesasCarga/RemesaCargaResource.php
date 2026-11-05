<?php

namespace App\Filament\App\Resources\RemesasCarga;

use App\Filament\App\Carga\AccionesRndc;
use App\Filament\App\Resources\RemesasCarga\Pages\CrearRemesa;
use App\Filament\App\Resources\RemesasCarga\Pages\EditarRemesa;
use App\Filament\App\Resources\RemesasCarga\Pages\ListarRemesas;
use App\Filament\App\Resources\RemesasCarga\Schemas\RemesaCargaForm;
use App\Models\RemesaCarga;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use UnitEnum;

/** Remesas terrestres de carga: documento oficial por envío (proceso 3 del RNDC). */
class RemesaCargaResource extends Resource
{
    protected static ?string $model = RemesaCarga::class;

    protected static ?string $slug = 'remesas-carga';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Carga';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Remesas';

    protected static ?string $modelLabel = 'remesa';

    protected static ?string $pluralModelLabel = 'remesas terrestres';

    protected static ?string $tenantRelationshipName = 'remesasCarga';

    public static function form(Schema $schema): Schema
    {
        return RemesaCargaForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['remitente', 'destinatario', 'manifiesto', 'ultimoRegistroRndc']))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Expida su primera remesa')
            ->columns([
                TextColumn::make('numero')->label('No.')->formatStateUsing(fn (RemesaCarga $r) => $r->numeroFormateado())->weight('bold'),
                TextColumn::make('fecha')->date('d/m/Y'),
                TextColumn::make('producto_descripcion')->label('Producto')->wrap()->limit(40)->description(fn (RemesaCarga $r) => RemesaCarga::OPERACIONES[$r->operacion] ?? ''),
                TextColumn::make('cantidad')->numeric(thousandsSeparator: '.')->suffix(fn (RemesaCarga $r) => $r->unidad === 2 ? ' gal' : ' kg')->alignEnd(),
                TextColumn::make('remitente.nombre_completo')->label('Remitente')->limit(28)->visibleFrom('md'),
                TextColumn::make('destinatario.nombre_completo')->label('Destinatario')->limit(28)->visibleFrom('lg'),
                TextColumn::make('manifiesto.numero')->label('Manifiesto')->formatStateUsing(fn ($state, RemesaCarga $r) => $r->manifiesto?->numeroFormateado())->placeholder('—')->visibleFrom('md'),
                AccionesRndc::columnaEstado(),
            ])
            ->filters([
                SelectFilter::make('operacion')->options(RemesaCarga::OPERACIONES),
                SelectFilter::make('sin_manifiesto')->label('Sin manifiesto')->query(fn ($query, array $data) => $data['value'] ? $query->whereNull('manifiesto_id') : $query),
            ])
            ->recordActions([
                AccionesRndc::grupo(),
                EditAction::make()->visible(fn (RemesaCarga $r) => ! $r->manifiesto_id && ! $r->anulado()),
                Action::make('anular')->label('Anular')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                    ->visible(fn (RemesaCarga $r) => auth()->user()?->can('anular_remesas_carga') && ! $r->anulado() && ! $r->manifiesto_id)
                    ->schema([\Filament\Forms\Components\Textarea::make('motivo')->required()->maxLength(200)])
                    ->action(function (RemesaCarga $r, array $data): void {
                        $r->update(['anulado_at' => now(), 'motivo_anulacion' => $data['motivo']]);
                        Notification::make()->success()->title('Remesa anulada')->body('Reporte la anulación al RNDC (proceso 9) cuando esté disponible.')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarRemesas::route('/'),
            'crear' => CrearRemesa::route('/crear'),
            'editar' => EditarRemesa::route('/{record}/editar'),
        ];
    }
}
