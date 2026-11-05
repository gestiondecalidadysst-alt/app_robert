<?php

namespace App\Filament\App\Resources\ManifiestosCarga;

use App\Filament\App\Carga\AccionesRndc;
use App\Filament\App\Carga\AnularConRndc;
use App\Filament\App\Resources\ManifiestosCarga\Pages\CrearManifiesto;
use App\Filament\App\Resources\ManifiestosCarga\Pages\EditarManifiesto;
use App\Filament\App\Resources\ManifiestosCarga\Pages\ListarManifiestos;
use App\Filament\App\Resources\ManifiestosCarga\Schemas\ManifiestoCargaForm;
use App\Models\ManifiestoCarga;
use App\Models\RemesaCarga;
use App\Support\Carga\AsociadorDeRemesas;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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

    /** Cuando el selector sale vacío, explica el motivo en vez de dejar la lista en blanco. */
    private static function porqueNoHayRemesas(ManifiestoCarga $m): string
    {
        $libres = RemesaCarga::query()
            ->where(fn ($query) => $query->whereNull('manifiesto_id')->orWhere('manifiesto_id', $m->id))
            ->whereNull('anulado_at')->exists();

        if ($libres) {
            return 'Seleccione una o más remesas…';
        }

        return RemesaCarga::query()->exists()
            ? 'No hay remesas disponibles: todas están en otro manifiesto o anuladas.'
            : 'Todavía no ha creado ninguna remesa (Carga → Remesas).';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['origen', 'destino', 'vehiculo', 'conductor', 'remesas', 'cumplido', 'ultimoRegistroRndc']))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Expida su primer manifiesto')
            ->columns([
                TextColumn::make('numero')->label('No.')->formatStateUsing(fn (ManifiestoCarga $m) => $m->numeroFormateado())->weight('bold'),
                TextColumn::make('fecha_expedicion')->label('Fecha')->date('d/m/Y'),
                TextColumn::make('ruta')->label('Ruta')->state(fn (ManifiestoCarga $m) => ($m->origen?->nombre ?? '—').' → '.($m->destino?->nombre ?? '—')),
                TextColumn::make('vehiculo.placa')->label('Vehículo')->description(fn (ManifiestoCarga $m) => $m->conductor?->nombre_completo),
                TextColumn::make('remesas_count')->label('Remesas')->counts('remesas')->badge()
                    ->color(fn ($state) => (int) $state === 0 ? 'danger' : 'success')
                    ->tooltip(fn ($state) => (int) $state === 0 ? 'Este manifiesto no tiene remesa: edítelo y marque una.' : null),
                TextColumn::make('valor_flete')->label('Flete')->numeric(thousandsSeparator: '.')->prefix('$ ')->alignEnd(),
                TextColumn::make('pago_saldo')->label('Saldo')->badge()
                    ->state(fn (ManifiestoCarga $m) => match ($estado = $m->estadoPagoSaldo()) {
                        'pagado' => 'Pagado',
                        'sin_cumplido' => 'Sin cumplir',
                        'vencido' => 'Vencido',
                        'por_vencer' => 'Por vencer',
                        default => 'A tiempo',
                    })
                    ->color(fn (ManifiestoCarga $m) => match ($m->estadoPagoSaldo()) {
                        'pagado' => 'success', 'vencido' => 'danger', 'por_vencer' => 'warning', default => 'gray',
                    })
                    ->tooltip(fn (ManifiestoCarga $m) => ($limite = $m->fechaLimitePagoSaldo())
                        ? 'Plazo para pagar el saldo: '.$limite->format('d/m/Y').' (5 días hábiles desde el cumplido).'
                        : 'El plazo empieza a correr con el cumplido del manifiesto.')
                    ->toggleable(),
                AccionesRndc::columnaEstado(),
                TextColumn::make('anulado_at')->label('Anulado')->dateTime('d/m/Y')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo')->options(ManifiestoCarga::TIPOS),
                SelectFilter::make('saldo_vencido')->label('Pago del saldo')->placeholder('Todos')
                    ->options(['vencido' => 'Con plazo vencido'])
                    ->query(fn ($query, array $data) => ($data['value'] ?? null) === 'vencido'
                        ? $query->whereHas('cumplido')->whereRaw('(valor_flete - anticipo) > 0')
                        : $query),
                SelectFilter::make('anulados')->label('Mostrar')->placeholder('Vigentes')
                    ->options(['todos' => 'Todos', 'anulados' => 'Solo anulados'])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'todos' => $query, 'anulados' => $query->whereNotNull('anulado_at'), default => $query->whereNull('anulado_at'),
                    }),
            ])
            ->recordActions([
                AccionesRndc::grupo(),
                Action::make('remesas')->label('Remesas')->icon('heroicon-o-paper-clip')
                    ->color(fn (ManifiestoCarga $m) => $m->remesas()->count() === 0 ? 'danger' : 'gray')
                    ->modalHeading('Remesas del manifiesto')
                    ->modalSubmitActionLabel('Asociar')
                    // Cambia el contenido del manifiesto: exige el mismo permiso que editarlo.
                    ->visible(fn (ManifiestoCarga $m) => (auth()->user()?->can('editar_manifiestos_carga') ?? false)
                        && ! $m->anulado() && $m->estadoRndc() !== 'registrado')
                    ->fillForm(fn (ManifiestoCarga $m) => ['remesas_ids' => $m->remesas()->pluck('id')->all()])
                    ->schema([
                        Select::make('remesas_ids')->label('Remesas')->multiple()->required()->searchable()->native(false)
                            ->options(fn (ManifiestoCarga $m) => RemesaCarga::query()
                                ->where(fn ($query) => $query->whereNull('manifiesto_id')->orWhere('manifiesto_id', $m->id))
                                ->whereNull('anulado_at')->orderBy('id', 'desc')->limit(200)->get()
                                ->mapWithKeys(fn ($r) => [$r->id => $r->numeroFormateado().' · '.$r->producto_descripcion]))
                            ->helperText('Solo aparecen las remesas que aún no están en otro manifiesto.')
                            ->placeholder(fn (ManifiestoCarga $m) => self::porqueNoHayRemesas($m)),
                    ])
                    ->action(function (ManifiestoCarga $m, array $data): void {
                        $asociadas = AsociadorDeRemesas::aplicar($m, $data['remesas_ids'] ?? []);

                        if ($asociadas === 0) {
                            Notification::make()->danger()->title('No se asoció ninguna remesa')
                                ->body('La remesa puede estar anulada o pertenecer a otra empresa. Avísenos para revisarlo.')
                                ->persistent()->send();

                            return;
                        }

                        Notification::make()->success()
                            ->title($asociadas === 1 ? 'Remesa asociada' : $asociadas.' remesas asociadas')
                            ->body('Vuelva a descargar el PDF: ya saldrá la mercancía.')->send();
                    }),
                Action::make('pdf')->label('Manifiesto (PDF)')->icon('heroicon-o-document-arrow-down')
                    ->url(fn (ManifiestoCarga $m) => $m->urlPdf(), shouldOpenInNewTab: true),
                // A la página completa, no a un modal: el asistente son 4 pasos y en el
                // modal la barra de pasos se corta y aparecen dos juegos de botones.
                EditAction::make()->url(fn (ManifiestoCarga $m) => EditarManifiesto::getUrl(['record' => $m]))
                    ->visible(fn (ManifiestoCarga $m) => ! $m->anulado() && $m->estadoRndc() !== 'registrado'),
                AnularConRndc::manifiesto(),
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
