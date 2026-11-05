<?php

namespace App\Filament\App\Resources\Servicios\Schemas;

use App\Filament\App\Resources\Fuecs\Schemas\FuecForm;
use App\Filament\Compartido\CamposComunes;
use App\Models\Contrato;
use App\Models\Ruta;
use App\Models\Servicio;
use App\Support\Operacion\Jornada;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Formulario del servicio planificado. Todo es opcional salvo fecha, origen y destino (el plan es
 * libre). Debajo muestra en vivo los avisos de jornada y cruces (no bloquean).
 * Con extracto, solo se editan la ruta, la conducción estimada, los pasajeros y las observaciones.
 */
final class ServicioForm
{
    public static function configure(Schema $schema): Schema
    {
        $bloqueado = fn (?Servicio $record) => (bool) $record?->fuec_id;

        return $schema->components([
            Callout::make('Este servicio ya tiene extracto')
                ->description('El vehículo, los conductores, las fechas, las horas, el origen y el destino son los del FUEC. Aquí solo se ajustan el rutograma, la conducción estimada, los pasajeros y las observaciones.')
                ->status('info')->columnSpanFull()
                ->visible($bloqueado),

            Section::make('Servicio')
                ->icon('heroicon-o-calendar-days')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Select::make('contrato_id')->label('Contrato')
                        ->options(fn () => Contrato::query()->noAnulados()->whereDate('fecha_fin', '>=', today()->subDays(30))->with('cliente')->orderByDesc('numero')->get()
                            ->mapWithKeys(fn (Contrato $c) => [$c->id => "{$c->numero_formateado} · {$c->cliente?->nombre} · {$c->vigencia}"]))
                        ->searchable()->live()->placeholder('Sin contrato todavía')
                        ->helperText('Opcional. Se necesita para generar el extracto.')
                        ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => self::aplicarContrato($set, $get, $state))
                        ->disabled($bloqueado)->columnSpan(['md' => 3]),
                    DatePicker::make('fecha_inicio')->label('Fecha de salida')->required()->default(today())->live()->disabled($bloqueado),
                    DatePicker::make('fecha_fin')->label('Hasta (si se repite varios días)')->afterOrEqual('fecha_inicio')->live()->disabled($bloqueado),
                    TextInput::make('pasajeros')->numeric()->minValue(1)->maxValue(999),
                    TimePicker::make('hora_salida')->label('Hora de salida')->seconds(false)->live()->disabled($bloqueado),
                    TimePicker::make('hora_llegada')->label('Hora de llegada estimada')->seconds(false)->live()->disabled($bloqueado)
                        ->helperText('Si es menor que la de salida, llega al día siguiente.'),
                ]),

            Section::make('Ruta')
                ->icon('heroicon-o-map')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    CamposComunes::municipio('origen_municipio_id', 'Origen')->required()->live()->disabled($bloqueado)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::sugerirRuta($get, $set)),
                    CamposComunes::municipio('destino_municipio_id', 'Destino')->required()->live()->disabled($bloqueado)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::sugerirRuta($get, $set)),
                    Select::make('ruta_id')->label('Rutograma')
                        ->options(fn () => Ruta::query()->activas()->orderBy('nombre')->pluck('nombre', 'id'))
                        ->searchable()->live()->placeholder('Sin rutograma')
                        ->helperText(fn (Get $get) => $get('ruta_id') ? null : 'Se sugiere solo el que corresponde al origen y destino. Créelo en Operación → Rutogramas.'),
                    TextInput::make('conduccion_min')->label('Conducción estimada por día (min)')->numeric()->minValue(1)->maxValue(1440)->live(onBlur: true)
                        ->placeholder(fn (Get $get) => ($r = Ruta::find($get('ruta_id'))) && $r->duracion_min ? "{$r->duracion_min} (del rutograma)" : 'La franja horaria')
                        ->helperText('Para el control de jornada. Vacío = la del rutograma o la franja horaria.'),
                    Textarea::make('recorrido')->label('Recorrido')->rows(2)->columnSpan(['md' => 2])->disabled($bloqueado),
                ]),

            Section::make('Vehículo y conductores')
                ->description('Opcional al planificar. Se exigen al generar el extracto.')
                ->icon('heroicon-o-truck')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    Select::make('vehiculo_id')->label('Vehículo')
                        ->options(fn () => FuecForm::opcionesVehiculos())->searchable()->live()->disabled($bloqueado)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => FuecForm::sugerirConductores($set, $state)),
                    ...collect(FuecForm::CAMPOS_CONDUCTOR)->map(fn (string $campo, int $i) => Select::make($campo)
                        ->label('Conductor '.($i + 1))
                        ->options(fn () => FuecForm::opcionesConductores())
                        ->searchable()->live()->disabled($bloqueado))->all(),
                ]),

            Callout::make('Avisos de jornada y disponibilidad')
                ->columnSpanFull()
                ->status('warning')
                ->icon('heroicon-o-exclamation-triangle')
                ->description(fn (Get $get, ?Servicio $record) => new HtmlString(collect(self::avisos($get, $record))->map(fn ($m) => '• '.e($m))->implode('<br>')
                    .'<br><span style="opacity:.75">Solo son avisos: puede guardar el plan.</span>'))
                ->visible(fn (Get $get, ?Servicio $record) => self::avisos($get, $record) !== []),

            Textarea::make('observaciones')->rows(2)->columnSpanFull(),
        ]);
    }

    /** Datos del formulario como los recibe PlanificarServicio. */
    public static function aDatos(array $estado): array
    {
        return [
            ...collect($estado)->only([
                'contrato_id', 'ruta_id', 'vehiculo_id', 'fecha_inicio', 'fecha_fin', 'hora_salida', 'hora_llegada',
                'origen_municipio_id', 'destino_municipio_id', 'recorrido', 'conduccion_min', 'pasajeros', 'observaciones',
            ])->all(),
            'conductores' => array_values(array_filter(array_map(fn ($c) => $estado[$c] ?? null, FuecForm::CAMPOS_CONDUCTOR))),
        ];
    }

    /** Valores del servicio para llenar el formulario (conductores en conductor_1..3). */
    public static function aFormulario(Servicio $servicio): array
    {
        $ids = $servicio->conductores()->pluck('conductores.id')->values();

        return [
            ...$servicio->only(['contrato_id', 'ruta_id', 'vehiculo_id', 'origen_municipio_id', 'destino_municipio_id', 'recorrido', 'conduccion_min', 'pasajeros', 'observaciones']),
            'fecha_inicio' => $servicio->fecha_inicio?->toDateString(),
            'fecha_fin' => $servicio->fecha_fin?->toDateString(),
            'hora_salida' => $servicio->hora_salida ? substr((string) $servicio->hora_salida, 0, 5) : null,
            'hora_llegada' => $servicio->hora_llegada ? substr((string) $servicio->hora_llegada, 0, 5) : null,
            ...collect(FuecForm::CAMPOS_CONDUCTOR)->mapWithKeys(fn ($c, $i) => [$c => $ids[$i] ?? null])->all(),
        ];
    }

    private static function aplicarContrato(Set $set, Get $get, ?string $id): void
    {
        $contrato = $id ? Contrato::find($id) : null;
        if (! $contrato) {
            return;
        }
        foreach ([
            'origen_municipio_id' => $contrato->origen_municipio_id,
            'destino_municipio_id' => $contrato->destino_municipio_id,
            'recorrido' => $contrato->recorrido,
            'hora_salida' => $contrato->hora_inicio ? substr((string) $contrato->hora_inicio, 0, 5) : null,
            'hora_llegada' => $contrato->hora_fin ? substr((string) $contrato->hora_fin, 0, 5) : null,
            'pasajeros' => $contrato->cantidad_pasajeros,
        ] as $campo => $valor) {
            if (blank($get($campo)) && filled($valor)) {
                $set($campo, $valor);
            }
        }
        self::sugerirRuta($get, $set);
    }

    private static function sugerirRuta(Get $get, Set $set): void
    {
        $ruta = Ruta::sugerida(Filament::getTenant()->id, (int) $get('origen_municipio_id'), (int) $get('destino_municipio_id'));
        if ($ruta) {
            $set('ruta_id', $ruta->id);
            if (blank($get('recorrido')) && $ruta->recorrido) {
                $set('recorrido', $ruta->recorrido);
            }
        }
    }

    /** @return list<string> */
    public static function avisos(Get $get, ?Servicio $record): array
    {
        if (blank($get('fecha_inicio'))) {
            return [];
        }

        $s = $record ? $record->replicate()->forceFill(['id' => $record->id]) : new Servicio;
        $s->exists = (bool) $record;
        $s->empresa_id = Filament::getTenant()->id;
        if (! $record?->fuec_id) {
            $s->fecha_inicio = Carbon::parse($get('fecha_inicio'));
            $s->fecha_fin = Carbon::parse($get('fecha_fin') ?: $get('fecha_inicio'));
            $s->hora_salida = $get('hora_salida') ?: null;
            $s->hora_llegada = $get('hora_llegada') ?: null;
            $s->vehiculo_id = $get('vehiculo_id') ?: null;
        }
        $s->conduccion_min = filled($get('conduccion_min')) ? (int) $get('conduccion_min') : null;
        $s->setRelation('ruta', $get('ruta_id') ? Ruta::find($get('ruta_id')) : null);

        if ($s->fecha_fin->lt($s->fecha_inicio)) {
            return [];
        }

        $conductores = $record?->fuec_id
            ? $record->conductores()->pluck('conductores.id')->all()
            : array_values(array_filter(array_map(fn ($c) => $get($c), FuecForm::CAMPOS_CONDUCTOR)));

        return Jornada::avisos($s, $conductores);
    }
}
