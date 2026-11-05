<?php

namespace App\Filament\App\Resources\ManifiestosCarga;

use App\Filament\App\Carga\AccionesRndc;
use App\Filament\App\Resources\ManifiestosCarga\Pages\CrearManifiesto;
use App\Filament\App\Resources\ManifiestosCarga\Pages\EditarManifiesto;
use App\Filament\App\Resources\ManifiestosCarga\Pages\ListarManifiestos;
use App\Filament\App\Resources\ManifiestosCarga\Schemas\ManifiestoCargaForm;
use App\Models\ManifiestoCarga;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Manifiestos electrónicos de carga (RNDC, proceso 4). */
class ManifiestoCargaResource extends Resource
{
    protected static ?string $model = ManifiestoCarga::class;

    protected static ?string $slug = 'manifiestos-carga';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Carga';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Manifiestos';

    protected static ?string $modelLabel = 'manifiesto';

    protected static ?string $pluralModelLabel = 'manifiestos electrónicos';

    protected static ?string $tenantRelationshipName = 'manifiestosCarga';

    public static function form(Schema $schema): Schema
    {
        return ManifiestoCargaForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['origen', 'destino', 'vehiculo', 'conductor', 'remesas', 'ultimoRegistroRndc']))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Expida su primer manifiesto')
            ->columns([
                TextColumn::make('numero')->label('No.')->formatStateUsing(fn (ManifiestoCarga $m) => $m->numeroFormateado())->weight('bold'),
                TextColumn::make('fecha_expedicion')->label('Fecha')->date('d/m/Y'),
                TextColumn::make('ruta')->label('Ruta')->state(fn (ManifiestoCarga $m) => ($m->origen?->nombre ?? '—') . ' → ' . ($m->destino?->nombre ?? '—')),
                TextColumn::make('vehiculo.placa')->label('Vehículo')->description(fn (ManifiestoCarga $m) => $m->conductor?->nombre_completo),
                TextColumn::make('remesas_count')->label('Remesas')->counts('remesas')->badge(),
                TextColumn::make('valor_flete')->label('Flete')->numeric(thousandsSeparator: '.')->prefix('$ ')->alignEnd(),
                AccionesRndc::columnaEstado(),
                TextColumn::make('anulado_at')->label('Anulado')->dateTime('d/m/Y')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo')->options(ManifiestoCarga::TIPOS),
                SelectFilter::make('anulados')->label('Mostrar')->placeholder('Vigentes')
                    ->options(['todos' => 'Todos', 'anulados' => 'Solo anulados'])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'todos' => $query, 'anulados' => $query->whereNotNull('anulado_at'), default => $query->whereNull('anulado_at'),
                    }),
            ])
            ->recordActions([
                AccionesRndc::grupo(),
                Action::make('pdf')->label('Manifiesto (PDF)')->icon('heroicon-o-document-arrow-down')
                    ->url(fn (ManifiestoCarga $m) => $m->urlPdf(), shouldOpenInNewTab: true),
                EditAction::make()->visible(fn (ManifiestoCarga $m) => ! $m->anulado() && $m->estadoRndc() !== 'registrado'),
                Action::make('anular')->label('Anular')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                    ->visible(fn (ManifiestoCarga $m) => auth()->user()?->can('anular_manifiestos_carga') && ! $m->anulado())
                    ->schema([\Filament\Forms\Components\Select::make('motivo')->options(['D' => 'Error en la digitación', 'S' => 'Cancelación del servicio'])->required()])
                    ->action(function (ManifiestoCarga $m, array $data): void {
                        $m->update(['anulado_at' => now(), 'motivo_anulacion' => $data['motivo']]);
                        Notification::make()->success()->title('Manifiesto anulado')->body('Reporte la anulación al RNDC (proceso 32) cuando esté disponible.')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarManifiestos::route('/'),
            'crear' => CrearManifiesto::route('/crear'),
            'editar' => EditarManifiesto::route('/{record}/editar'),
        ];
    }
}
