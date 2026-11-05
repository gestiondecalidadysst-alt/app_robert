<?php

namespace App\Filament\App\Resources\ManifiestosCarga\Schemas;

use App\Models\Conductor;
use App\Models\ManifiestoCarga;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\RemesaCarga;
use App\Models\Remolque;
use App\Models\TerceroCarga;
use App\Models\Vehiculo;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Formulario del manifiesto como asistente por pasos: viaje → vehículo y titular → remesas → pago.
 * Cada paso valida lo suyo antes de permitir avanzar; los campos son de ancho completo o en dos
 * columnas máximo, así los selects y placeholders se ven enteros.
 */
final class ManifiestoCargaForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                self::pasoViaje(),
                self::pasoVehiculo(),
                self::pasoRemesas(),
                self::pasoPago(),
            ])
                ->columnSpanFull()
                ->skippable(false)
                ->submitAction(view('filament.app.carga.boton-expedir')),
        ]);
    }

    private static function pasoViaje(): Step
    {
        return Step::make('Viaje')
            ->icon('heroicon-o-map')
            ->description('¿Qué tipo de manifiesto y de dónde a dónde?')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Select::make('tipo')->label('Tipo de manifiesto')->options(ManifiestoCarga::TIPOS)
                    ->default('G')->required()->native(false)
                    ->helperText('General para la mayoría; paqueteo si son muchos envíos pequeños.'),
                DatePicker::make('fecha_expedicion')->label('Fecha de expedición')->required()->default(today())->native(false),
                Select::make('origen_id')->label('Municipio de origen')->required()->searchable()->preload(false)
                    ->relationship('origen', 'nombre', fn ($query) => $query->orderBy('nombre'))
                    ->getOptionLabelFromRecordUsing(fn (Municipio $m) => $m->nombre_completo)
                    ->placeholder('Escriba el nombre del municipio…'),
                Select::make('destino_id')->label('Municipio de destino')->required()->searchable()->preload(false)
                    ->relationship('destino', 'nombre', fn ($query) => $query->orderBy('nombre'))
                    ->getOptionLabelFromRecordUsing(fn (Municipio $m) => $m->nombre_completo)
                    ->placeholder('Escriba el nombre del municipio…'),
                Select::make('municipio_intermedio_id')->label('Municipio intermedio (opcional)')->searchable()->preload(false)
                    ->relationship('municipioIntermedio', 'nombre', fn ($query) => $query->orderBy('nombre'))
                    ->getOptionLabelFromRecordUsing(fn (Municipio $m) => $m->nombre_completo)
                    ->placeholder('Si pasa por un punto intermedio relevante.')
                    ->columnSpanFull(),
                \Filament\Forms\Components\Textarea::make('via_utilizar')->label('Vía a utilizar (opcional)')->rows(2)->maxLength(300)
                    ->placeholder('Describa la ruta, por ejemplo: «Se cambia paso por Calarcá, Pereira, Cartago».')
                    ->columnSpanFull(),
                Textarea::make('observaciones')->label('Observaciones (opcional)')->rows(2)->maxLength(200)->columnSpanFull()
                    ->placeholder('Teléfonos de emergencia o condiciones especiales del viaje.'),
            ]);
    }

    private static function pasoVehiculo(): Step
    {
        return Step::make('Vehículo y titular')
            ->icon('heroicon-o-truck')
            ->description('¿Qué vehículo, qué conductor y a nombre de quién va el manifiesto?')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Select::make('vehiculo_id')->label('Vehículo (camión o tractocamión)')->required()->searchable()->native(false)
                    ->options(fn () => Vehiculo::query()->whereNotNull('rndc_configuracion')->orderBy('placa')
                        ->get()->mapWithKeys(fn (Vehiculo $v) => [$v->id => $v->placa . ($v->marca?->nombre ? ' · ' . $v->marca->nombre : '')]))
                    ->placeholder(fn () => Vehiculo::query()->whereNotNull('rndc_configuracion')->exists() ? 'Seleccione el vehículo…' : 'No hay vehículos de carga: complete la pestaña «Carga (RNDC)» en un vehículo.')
                    ->hint(fn () => Vehiculo::query()->whereNotNull('rndc_configuracion')->exists() ? null : new \Illuminate\Support\HtmlString('<a href="/app/' . \Filament\Facades\Filament::getTenant()?->slug . '/vehiculos" target="_blank" style="color:#ea6a2c;font-weight:500">Ir a vehículos ↗</a>'))
                    ->helperText('Solo aparecen los vehículos a los que ya se les llenó la pestaña «Carga (RNDC)» (configuración, peso vacío, marca, línea, color, carrocería, combustible, tenedor y SOAT).'),
                Select::make('remolque_id')->label('Remolque o semirremolque (si aplica)')->searchable()->native(false)
                    ->options(fn () => Remolque::query()->where('activo', true)->orderBy('placa')
                        ->get()->mapWithKeys(fn (Remolque $r) => [$r->id => $r->placa])),
                Select::make('remolque_2_id')->label('Segundo remolque (si va con dos)')->searchable()->native(false)
                    ->options(fn () => Remolque::query()->where('activo', true)->orderBy('placa')
                        ->get()->mapWithKeys(fn (Remolque $r) => [$r->id => $r->placa])),
                Select::make('conductor_id')->label('Conductor principal')->required()->searchable()->native(false)
                    ->options(fn () => Conductor::query()->vinculados()->orderBy('nombres')->get()
                        ->mapWithKeys(fn ($c) => [$c->id => $c->nombre_completo . ' · CC ' . $c->documento])),
                Select::make('conductor_2_id')->label('Segundo conductor (viajes largos, opcional)')->searchable()->native(false)
                    ->options(fn () => Conductor::query()->vinculados()->orderBy('nombres')->get()
                        ->mapWithKeys(fn ($c) => [$c->id => $c->nombre_completo . ' · CC ' . $c->documento])),
                Select::make('titular_propietario_id')->label('Titular: propietario o tenedor')->searchable()->native(false)
                    ->helperText('El titular del manifiesto puede ser el propietario o tenedor del vehículo, o un tercero. Elija solo uno de los dos.')
                    ->options(fn () => Propietario::query()->where('activo', true)->orderBy('nombres')->get()
                        ->mapWithKeys(fn ($p) => [$p->id => $p->nombre_completo . ' · ' . $p->documento])),
                Select::make('titular_tercero_id')->label('Titular: tercero (titular en comisión)')->searchable()->native(false)
                    ->helperText('Solo si el titular no es propietario ni tenedor del vehículo.')
                    ->options(fn () => TerceroCarga::query()->where('activo', true)->orderBy('nombre')->get()
                        ->mapWithKeys(fn ($t) => [$t->id => $t->nombre_completo . ' · ' . $t->documento])),
            ])
            ->afterValidation(function (array $state): void {
                if (blank($state['titular_propietario_id'] ?? null) && blank($state['titular_tercero_id'] ?? null)) {
                    throw ValidationException::withMessages(['titular_propietario_id' => 'Elija el titular del manifiesto (propietario, tenedor o tercero).']);
                }
                if (filled($state['titular_propietario_id'] ?? null) && filled($state['titular_tercero_id'] ?? null)) {
                    throw ValidationException::withMessages(['titular_tercero_id' => 'Elija solo uno: propietario/tenedor o tercero.']);
                }
            });
    }

    private static function pasoRemesas(): Step
    {
        return Step::make('Remesas')
            ->icon('heroicon-o-document-text')
            ->description('¿Qué remesas van en este viaje?')
            ->schema([
                Select::make('remesas_ids')->label('Remesas asociadas')->multiple()->required()->searchable()
                    ->options(fn ($record) => RemesaCarga::query()
                        ->where(fn ($q) => $q->whereNull('manifiesto_id')->orWhere('manifiesto_id', $record?->id))
                        ->whereNull('anulado_at')->orderBy('id', 'desc')->limit(200)->get()
                        ->mapWithKeys(fn ($r) => [$r->id => $r->numeroFormateado() . ' · ' . $r->producto_descripcion
                            . ($r->cantidad ? ' · ' . number_format($r->cantidad, 0, ',', '.') . ' ' . ($r->unidad === 2 ? 'gal' : 'kg') : '')]))
                    ->default(fn ($record) => $record?->remesas->pluck('id')->all() ?? [])
                    ->dehydrated(false)
                    ->afterStateHydrated(fn ($component, $record) => $component->state($record?->remesas->pluck('id')->all() ?? []))
                    ->helperText('Marque una o más remesas. Solo aparecen las que aún no están en otro manifiesto.')
                    ->columnSpanFull(),
            ])
            ->afterValidation(function (array $state): void {
                if (empty($state['remesas_ids'] ?? [])) {
                    throw ValidationException::withMessages(['remesas_ids' => 'El manifiesto debe tener al menos una remesa.']);
                }
            });
    }

    private static function pasoPago(): Step
    {
        return Step::make('Pago')
            ->icon('heroicon-o-banknotes')
            ->description('Flete, anticipo y saldo.')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                TextInput::make('valor_flete')->label('Valor del flete pactado')->required()->numeric()->minValue(1)->prefix('$')->inputMode('numeric'),
                TextInput::make('anticipo')->label('Anticipo al conductor')->numeric()->minValue(0)->default(0)->prefix('$')->inputMode('numeric'),
                TextInput::make('valor_minimo_sicetac')->label('Valor mínimo SICE-TAC (opcional)')->numeric()->minValue(0)->prefix('$')->inputMode('numeric')
                    ->helperText('Consúltelo en SICE-TAC. Si se deja vacío, no se valida; si se llena y el flete está por debajo, no deja expedir.'),
                Select::make('municipio_pago_id')->label('Municipio donde se paga el saldo')->searchable()->preload(false)
                    ->relationship('municipioPago', 'nombre', fn ($query) => $query->orderBy('nombre'))
                    ->getOptionLabelFromRecordUsing(fn (Municipio $m) => $m->nombre_completo)
                    ->placeholder('Si lo deja vacío, se asume el destino.'),
                DatePicker::make('fecha_pago_saldo')->label('Fecha de pago del saldo')->native(false)
                    ->helperText('Máximo 5 días hábiles después del cumplido (Resolución RNDC).'),
                TextInput::make('retencion_fuente_pct')->label('Retención en la fuente (%)')
                    ->numeric()->minValue(0)->maxValue(20)->step(0.01)->default(1)->suffix('%'),
                TextInput::make('reteica_por_mil')->label('Reteica (por 1.000)')
                    ->numeric()->minValue(0)->maxValue(20)->step(0.01)->default(0)->suffix(' x1000'),
                TextInput::make('retencion_fopat_pct')->label('Retención FOPAT (por 1.000)')
                    ->numeric()->minValue(0)->maxValue(20)->step(0.01)->default(1)->suffix(' x1000')
                    ->helperText('Fondo de Protección de los Actores de Transporte (Ley 2422/2024).'),
                Select::make('responsable_cargue')->label('Responsable del pago del cargue')->options(ManifiestoCarga::RESPONSABLES_PAGO)->default('E')->required()->native(false),
                Select::make('responsable_descargue')->label('Responsable del pago del descargue')->options(ManifiestoCarga::RESPONSABLES_PAGO)->default('E')->required()->native(false),
            ]);
    }

    /** @return array<int, string> */
    private static function municipios(string $busqueda): array
    {
        return Municipio::query()->with('departamento')->activos()
            ->where('nombre', 'like', "%{$busqueda}%")
            ->orderBy('nombre')->limit(30)->get()
            ->mapWithKeys(fn (Municipio $m) => [$m->id => $m->nombre_completo])->all();
    }
}
