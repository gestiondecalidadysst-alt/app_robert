<?php

namespace App\Filament\App\Resources\CumplidosManifiesto;

use App\Filament\App\Carga\AccionesRndc;
use App\Filament\App\Resources\CumplidosManifiesto\Pages\CrearCumplido;
use App\Filament\App\Resources\CumplidosManifiesto\Pages\EditarCumplido;
use App\Filament\App\Resources\CumplidosManifiesto\Pages\ListarCumplidos;
use App\Models\CumplidoManifiesto;
use App\Models\ManifiestoCarga;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Cumplidos del manifiesto (RNDC, proceso 6). */
class CumplidoManifiestoResource extends Resource
{
    protected static ?string $model = CumplidoManifiesto::class;

    protected static ?string $slug = 'cumplidos-manifiesto';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|UnitEnum|null $navigationGroup = 'Carga';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Cumplidos';

    protected static ?string $modelLabel = 'cumplido';

    protected static ?string $pluralModelLabel = 'cumplidos de manifiesto';

    protected static ?string $tenantRelationshipName = 'cumplidosManifiesto';

    public static function form(Schema $schema): Schema
    {
        $manifiestos = fn (?CumplidoManifiesto $record) => ManifiestoCarga::query()
            ->whereNull('anulado_at')
            ->where(fn ($q) => $q->whereDoesntHave('cumplido')->orWhere('id', $record?->manifiesto_id))
            ->with(['origen', 'destino'])->orderBy('id', 'desc')->limit(100)->get()
            ->mapWithKeys(fn (ManifiestoCarga $m) => [$m->id => $m->numeroFormateado().' · '.($m->origen?->nombre ?? '—').' → '.($m->destino?->nombre ?? '—')]);

        return $schema->columns(['default' => 1, 'md' => 3])->components([
            Section::make('Manifiesto que se cumple')
                ->columnSpan(['default' => 1, 'md' => 2])
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('manifiesto_id')->label('Manifiesto')->required()->searchable()->native(false)->options($manifiestos)->columnSpanFull()
                        ->helperText('Solo aparecen los manifiestos vigentes que aún no se han cumplido.'),
                    Select::make('tipo')->label('Tipo de cumplido')->options(CumplidoManifiesto::TIPOS)->default('C')->required()->live()->native(false)
                        ->helperText('Normal = el viaje se realizó como se registró. Suspensión = se realizó parte del viaje y hubo un cambio.'),
                    DatePicker::make('fecha_entrega_documentos')->label('Fecha de entrega de documentos')->required()->default(today())->maxDate(today())->native(false)
                        ->helperText('Día en que el conductor entregó los documentos para el pago.'),
                ]),
            Section::make('Resumen del pago')
                ->columnSpan(1)
                ->schema([Text::make(static::resumenPago())->columnSpanFull()]),
            Section::make('Suspensión')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->visible(fn (Get $get) => $get('tipo') === 'S')
                ->schema([
                    Select::make('motivo_suspension')->label('Motivo de la suspensión')->options(CumplidoManifiesto::MOTIVOS_SUSPENSION)->native(false)
                        ->required(fn (Get $get) => $get('tipo') === 'S'),
                    Select::make('consecuencia_suspension')->label('Qué se cambió')->options(CumplidoManifiesto::CONSECUENCIAS_SUSPENSION)->native(false)
                        ->required(fn (Get $get) => $get('tipo') === 'S'),
                ]),
            Section::make('Ajustes al pago (opcionales)')
                ->description('Reporte solo lo que difiere del manifiesto. El sistema recalcula el valor neto a pagar al titular.')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('valor_adicional_cargue')->label('Adicional por horas de cargue')->numeric()->minValue(0)->default(0)->prefix('$')->inputMode('numeric'),
                    TextInput::make('valor_adicional_descargue')->label('Adicional por horas de descargue')->numeric()->minValue(0)->default(0)->prefix('$')->inputMode('numeric'),
                    TextInput::make('valor_adicional_flete')->label('Otro adicional al flete')->numeric()->minValue(0)->default(0)->prefix('$')->inputMode('numeric'),
                    Select::make('motivo_adicional')->label('Motivo de los adicionales')->options(CumplidoManifiesto::MOTIVOS_ADICIONAL)->native(false)
                        ->helperText('Obligatorio si hay algún adicional.'),
                    TextInput::make('valor_descuento_flete')->label('Descuento al flete')->numeric()->minValue(0)->default(0)->prefix('$')->inputMode('numeric'),
                    Select::make('motivo_descuento')->label('Motivo del descuento')->options(CumplidoManifiesto::MOTIVOS_DESCUENTO)->native(false)
                        ->helperText('Obligatorio si hay descuento.'),
                    TextInput::make('valor_sobreanticipo')->label('Sobreanticipo entregado al conductor')->numeric()->minValue(0)->default(0)->prefix('$')->inputMode('numeric')
                        ->columnSpanFull()->helperText('No puede ser mayor que el saldo neto por pagar.'),
                ]),
            Section::make('Observaciones (opcional)')
                ->collapsed()
                ->columnSpanFull()
                ->schema([Textarea::make('observaciones')->rows(2)->maxLength(200)->hiddenLabel()]),
        ]);
    }

    /** Texto estático de ayuda en la columna derecha; el neto real se calcula al guardar. */
    private static function resumenPago(): string
    {
        return 'Al guardar, se recalcula el valor neto a pagar al titular con esta fórmula: '
            .'flete pactado ± adicionales − descuentos − anticipo del manifiesto − sobreanticipo − retefuente − reteica. '
            .'Verifique los ajustes antes de enviar al RNDC.';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['manifiesto.origen', 'manifiesto.destino', 'ultimoRegistroRndc']))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Registre su primer cumplido')
            ->columns([
                TextColumn::make('manifiesto.numero')->label('Manifiesto')->formatStateUsing(fn ($state, CumplidoManifiesto $c) => $c->manifiesto?->numeroFormateado())->weight('bold'),
                TextColumn::make('fecha_entrega_documentos')->label('Documentos')->date('d/m/Y'),
                TextColumn::make('tipo')->badge()->formatStateUsing(fn ($state) => CumplidoManifiesto::TIPOS[$state] ?? $state)->color(fn ($state) => $state === 'S' ? 'warning' : 'success'),
                TextColumn::make('neto')->label('Neto a pagar')->state(fn (CumplidoManifiesto $c) => $c->valorAPagar())
                    ->numeric(thousandsSeparator: '.')->prefix('$ ')->alignEnd(),
                AccionesRndc::columnaEstado(),
            ])
            ->recordActions([AccionesRndc::grupo(), EditAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarCumplidos::route('/'),
            'crear' => CrearCumplido::route('/crear'),
            'editar' => EditarCumplido::route('/{record}/editar'),
        ];
    }
}
