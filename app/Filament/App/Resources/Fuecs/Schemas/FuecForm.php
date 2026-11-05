<?php

namespace App\Filament\App\Resources\Fuecs\Schemas;

use App\Actions\Operacion\ExpedirFuec;
use App\Filament\Compartido\CamposComunes;
use App\Models\AsignacionVehiculo;
use App\Models\Conductor;
use App\Models\Contrato;
use App\Models\EmpresaConvenio;
use App\Models\Vehiculo;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Formulario "Expedir FUEC". Debajo muestra la revisión en vivo (ExpedirFuec::validar),
 * para que se vea qué bloquea antes de enviar.
 */
final class FuecForm
{
    public const CAMPOS_CONDUCTOR = ['conductor_1', 'conductor_2', 'conductor_3'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contrato y vigencia')
                ->icon('heroicon-o-document-text')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Select::make('contrato_id')
                        ->label('Contrato')
                        ->options(fn () => Contrato::query()->disponiblesParaFuec()->with('cliente')->orderByDesc('numero')->get()
                            ->mapWithKeys(fn (Contrato $c) => [$c->id => "{$c->numero_formateado} · {$c->cliente?->nombre} · {$c->vigencia}"]))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set, ?string $state) => self::aplicarContrato($set, $state))
                        ->columnSpan(['md' => 3]),
                    DatePicker::make('fecha_inicio')->label('Fecha inicial')->required()->minDate(today())->live(),
                    DatePicker::make('fecha_fin')->label('Fecha de vencimiento')->required()->afterOrEqual('fecha_inicio')->live()
                        ->helperText('No puede superar la vigencia del contrato.')
                        ->hintAction(
                            Action::make('usarFechaMaxima')
                                ->label('Usar fecha máxima')
                                ->icon('heroicon-m-calendar')
                                ->visible(fn (Get $get) => self::fechaMaxima($get) !== null)
                                ->action(fn (Get $get, Set $set) => $set('fecha_fin', self::fechaMaxima($get)?->toDateString())),
                        ),
                    TimePicker::make('hora_inicio')->label('Hora de inicio')->seconds(false)->live()
                        ->helperText('Del contrato. Vacía = todo el día.'),
                    TimePicker::make('hora_fin')->label('Hora de terminación')->seconds(false)->live()
                        ->helperText('Si es menor que la de inicio, el servicio pasa la medianoche.'),
                    Select::make('empresa_convenio_id')
                        ->label('Convenio de colaboración')
                        ->options(fn () => EmpresaConvenio::query()->where('activo', true)->orderBy('razon_social')->pluck('razon_social', 'id'))
                        ->placeholder('No aplica')
                        ->helperText('Solo si el servicio se presta con un vehículo en convenio (art. 13).'),
                ]),

            Section::make('Vehículo y conductores')
                ->icon('heroicon-o-truck')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    Select::make('vehiculo_id')
                        ->label('Vehículo')
                        ->options(fn () => self::opcionesVehiculos())
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set, ?string $state) => self::sugerirConductores($set, $state))
                        ->helperText('Al elegirlo se proponen sus conductores asignados. ⚠ = hoy no puede operar.'),
                    ...collect(self::CAMPOS_CONDUCTOR)->map(fn (string $campo, int $i) => Select::make($campo)
                        ->label('Conductor '.($i + 1))
                        ->options(fn () => self::opcionesConductores())
                        ->searchable()
                        ->required($i === 0)
                        ->live())->all(),
                ]),

            Section::make('Origen, destino y recorrido')
                ->icon('heroicon-o-map-pin')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    CamposComunes::municipio('origen_municipio_id', 'Origen')->required()->live(),
                    CamposComunes::municipio('destino_municipio_id', 'Destino')->required()->live(),
                    Textarea::make('recorrido')->label('Recorrido')->rows(2)->columnSpanFull(),
                ]),

            Callout::make(fn (Get $get) => self::errores($get) === [] ? 'Listo para expedir' : 'Revise antes de expedir')
                ->columnSpanFull()
                ->status(fn (Get $get) => self::errores($get) === [] ? 'success' : 'danger')
                ->icon(fn (Get $get) => self::errores($get) === [] ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->description(fn (Get $get) => self::errores($get) === []
                    ? 'El contrato, el vehículo y los conductores cumplen para toda la vigencia y no tienen otro servicio a la misma hora.'
                    : new HtmlString(collect(self::errores($get))->flatten()->map(fn ($m) => '• '.e($m))->implode('<br>')))
                ->visible(fn (Get $get) => filled($get('contrato_id')) && filled($get('vehiculo_id'))),
        ]);
    }

    /** Datos del formulario como los recibe ExpedirFuec. */
    public static function aDatos(array $estado): array
    {
        return [
            'contrato_id' => $estado['contrato_id'] ?? null,
            'vehiculo_id' => $estado['vehiculo_id'] ?? null,
            'conductores' => array_values(array_filter(array_map(fn ($c) => $estado[$c] ?? null, self::CAMPOS_CONDUCTOR))),
            'fecha_inicio' => $estado['fecha_inicio'] ?? null,
            'fecha_fin' => $estado['fecha_fin'] ?? null,
            'hora_inicio' => $estado['hora_inicio'] ?? null,
            'hora_fin' => $estado['hora_fin'] ?? null,
            'origen_municipio_id' => $estado['origen_municipio_id'] ?? null,
            'destino_municipio_id' => $estado['destino_municipio_id'] ?? null,
            'recorrido' => $estado['recorrido'] ?? null,
            'empresa_convenio_id' => $estado['empresa_convenio_id'] ?? null,
        ];
    }

    /** Valores iniciales tomados del contrato (vigencia, origen, destino, recorrido). */
    public static function valoresDeContrato(int|string|null $contratoId): array
    {
        $contrato = $contratoId ? Contrato::query()->disponiblesParaFuec()->find($contratoId) : null;

        if (! $contrato) {
            return ['fecha_inicio' => today()->toDateString()];
        }

        return [
            'contrato_id' => $contrato->id,
            'fecha_inicio' => Carbon::parse(max($contrato->fecha_inicio->toDateString(), today()->toDateString()))->toDateString(),
            'fecha_fin' => $contrato->fecha_fin->toDateString(),
            'hora_inicio' => $contrato->hora_inicio ? substr((string) $contrato->hora_inicio, 0, 5) : null,
            'hora_fin' => $contrato->hora_fin ? substr((string) $contrato->hora_fin, 0, 5) : null,
            'origen_municipio_id' => $contrato->origen_municipio_id,
            'destino_municipio_id' => $contrato->destino_municipio_id,
            'recorrido' => $contrato->recorrido,
        ];
    }

    private static function aplicarContrato(Set $set, ?string $contratoId): void
    {
        foreach (self::valoresDeContrato($contratoId) as $campo => $valor) {
            $set($campo, $valor);
        }
    }

    public static function sugerirConductores(Set $set, ?string $vehiculoId): void
    {
        if (! $vehiculoId) {
            return;
        }

        $sugeridos = AsignacionVehiculo::query()
            ->where('vehiculo_id', $vehiculoId)
            ->vigentes()
            ->whereHas('conductor', fn ($q) => $q->vinculados())
            ->orderByDesc('principal')
            ->orderBy('fecha_desde')
            ->limit(count(self::CAMPOS_CONDUCTOR))
            ->pluck('conductor_id')
            ->values();

        foreach (self::CAMPOS_CONDUCTOR as $i => $campo) {
            $set($campo, $sugeridos[$i] ?? null);
        }
    }

    public static function opcionesVehiculos(): array
    {
        return Vehiculo::query()->enFlota()->with(['marca', 'linea', 'documentos'])->orderBy('placa')->get()
            ->mapWithKeys(fn (Vehiculo $v) => [$v->id => ($v->puedeOperar() ? '' : '⚠ ').trim("{$v->placa} · {$v->descripcion}").($v->numero_interno ? " · int. {$v->numero_interno}" : '')])
            ->all();
    }

    public static function opcionesConductores(): array
    {
        return Conductor::query()->vinculados()->with(['documentos.categoriaLicencia'])->orderBy('nombres')->get()
            ->mapWithKeys(fn (Conductor $c) => [$c->id => ($c->puedeOperar() ? '' : '⚠ ')."{$c->nombre_completo} · {$c->documento}"])
            ->all();
    }

    private static function fechaMaxima(Get $get): ?Carbon
    {
        return self::errores($get) === [] || ! isset(self::errores($get)['fecha_fin'])
            ? null
            : app(ExpedirFuec::class)->fechaMaxima(Filament::getTenant(), self::estadoActual($get));
    }

    private static function estadoActual(Get $get): array
    {
        return self::aDatos([
            ...collect(['contrato_id', 'vehiculo_id', 'fecha_inicio', 'fecha_fin', 'hora_inicio', 'hora_fin', 'origen_municipio_id', 'destino_municipio_id', 'empresa_convenio_id'])
                ->mapWithKeys(fn ($c) => [$c => $get($c)])->all(),
            ...collect(self::CAMPOS_CONDUCTOR)->mapWithKeys(fn ($c) => [$c => $get($c)])->all(),
        ]);
    }

    /** Revisión en vivo, memorizada por estado del formulario para no repetir consultas en el mismo render. */
    private static function errores(Get $get): array
    {
        static $cache = [];

        $datos = self::estadoActual($get);
        $clave = md5(serialize($datos));

        return $cache[$clave] ??= app(ExpedirFuec::class)->validar(Filament::getTenant(), $datos);
    }
}
