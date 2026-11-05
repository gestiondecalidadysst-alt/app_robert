<?php

namespace App\Support\Gerencia;

use App\Actions\Mantenimiento\ProgramaAnual;
use App\Enums\EstadoActividad;
use App\Enums\EstadoConductor;
use App\Enums\EstadoVehiculo;
use App\Enums\PrioridadPlan;
use App\Enums\ResultadoInspeccion;
use App\Enums\TipoMantenimiento;
use App\Filament\App\Resources\Vehiculos\VehiculoResource;
use App\Models\ActividadMantenimiento;
use App\Models\Conductor;
use App\Models\Contrato;
use App\Models\DocumentoConductor;
use App\Models\DocumentoVehiculo;
use App\Models\EventoServicio;
use App\Models\Fuec;
use App\Models\Inspeccion;
use App\Models\Mantenimiento;
use App\Models\PagoContrato;
use App\Models\PlanAccion;
use App\Models\Servicio;
use App\Models\Vehiculo;
use App\Support\Operacion\Jornada;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Indicadores del tablero de gerencia y del informe mensual. Todo sale de lo que ya se registra
 * (no se digita nada nuevo). Un objeto por empresa y periodo; la oficina (opcional) filtra lo que
 * tiene oficina: contratos, pagos (por su contrato) y extractos.
 *
 * Secciones: flota(), operacion(), cartera(), pesv(), mantenimiento() y tendencias() (12 meses).
 */
final class Indicadores
{
    public readonly Carbon $desde;

    public readonly Carbon $hasta;

    /** Último día que cuenta para lo "ya ocurrido" (hoy si el periodo sigue abierto). */
    public readonly Carbon $corte;

    public function __construct(
        public readonly int $empresaId,
        Carbon $desde,
        Carbon $hasta,
        public readonly ?int $oficinaId = null,
    ) {
        $this->desde = $desde->copy()->startOfDay();
        $this->hasta = $hasta->copy()->endOfDay();
        $this->corte = $this->hasta->copy()->min(today()->endOfDay());
    }

    /** Periodos del filtro: hoy, semana, mes, mes_anterior, anio, rango. */
    public static function periodo(string $periodo, ?string $desde = null, ?string $hasta = null): array
    {
        $hoy = today();

        return match ($periodo) {
            'hoy' => [$hoy->copy(), $hoy->copy()],
            'semana' => [$hoy->copy()->startOfWeek(Carbon::MONDAY), $hoy->copy()->endOfWeek(Carbon::SUNDAY)],
            'mes_anterior' => [$hoy->copy()->subMonthNoOverflow()->startOfMonth(), $hoy->copy()->subMonthNoOverflow()->endOfMonth()],
            'anio' => [$hoy->copy()->startOfYear(), $hoy->copy()->endOfYear()],
            'rango' => [Carbon::parse($desde ?: $hoy->copy()->startOfMonth()), Carbon::parse($hasta ?: $hoy)],
            default => [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()],
        };
    }

    public function etiquetaPeriodo(): string
    {
        return $this->desde->isSameDay($this->hasta->copy()->startOfDay())
            ? $this->desde->format('d/m/Y')
            : $this->desde->format('d/m/Y').' – '.$this->hasta->format('d/m/Y');
    }

    // ── Flota (estado a hoy) ──────────────────────────────────────────────

    public function flota(): array
    {
        $vehiculos = Vehiculo::query()->deEmpresa($this->empresaId)->enFlota();
        $porEstado = (clone $vehiculos)->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        $docsVehiculo = fn () => DocumentoVehiculo::query()->deEmpresa($this->empresaId)->vigentes()
            ->whereHas('vehiculo', fn (Builder $q) => $q->enFlota());
        $docsConductor = fn () => DocumentoConductor::query()->deEmpresa($this->empresaId)->vigentes()
            ->whereIn('tipo', array_map(fn ($t) => $t->value, Conductor::tiposObligatorios()))
            ->whereHas('conductor', fn (Builder $q) => $q->vinculados());

        return [
            'vehiculos' => (int) $porEstado->sum(),
            'operando' => (int) ($porEstado[EstadoVehiculo::Operando->value] ?? 0),
            'suspendidos' => (int) ($porEstado[EstadoVehiculo::Suspendido->value] ?? 0),
            'taller' => (int) ($porEstado[EstadoVehiculo::Taller->value] ?? 0),
            'sin_documentos' => VehiculoResource::conDocumentosVencidosOFaltantes(clone $vehiculos)->count(),
            'docs_vencidos' => $docsVehiculo()->vencidos()->count(),
            'docs_por_vencer' => $docsVehiculo()->porVencer()->count(),
            'conductores' => Conductor::query()->deEmpresa($this->empresaId)->where('estado', EstadoConductor::Activo->value)->count(),
            'conductores_suspendidos' => Conductor::query()->deEmpresa($this->empresaId)->where('estado', EstadoConductor::Suspendido->value)->count(),
            'licencias_vencidas' => $docsConductor()->vencidos()->count(),
            'licencias_por_vencer' => $docsConductor()->porVencer()->count(),
        ];
    }

    // ── Operación ─────────────────────────────────────────────────────────

    public function operacion(): array
    {
        $servicios = $this->servicios();
        $fuecs = $this->fuecs();
        $operando = max(1, Vehiculo::query()->deEmpresa($this->empresaId)->enEstado(EstadoVehiculo::Operando)->count());
        $usados = $servicios->whereNotNull('vehiculo_id')->pluck('vehiculo_id')->unique()->count();

        $clientes = Contrato::query()->deEmpresa($this->empresaId)->noAnulados()
            ->when($this->oficinaId, fn ($q) => $q->where('oficina_id', $this->oficinaId))
            ->whereBetween('created_at', [$this->desde, $this->hasta])
            ->with('cliente')->get()
            ->groupBy('cliente_id')
            ->map(fn (Collection $c) => ['cliente' => $c->first()->cliente?->nombre ?? '—', 'contratos' => $c->count(), 'valor' => (float) $c->sum('valor')])
            ->sortByDesc('valor')->take(5)->values();

        return [
            'servicios' => $servicios->count(),
            'servicios_con_extracto' => $servicios->whereNotNull('fuec_id')->count(),
            'servicios_sin_asignar' => $servicios->filter(fn (Servicio $s) => ! $s->vehiculo_id || $s->conductores->isEmpty())->count(),
            'extractos' => $fuecs->whereNull('anulado_at')->count(),
            'extractos_anulados' => $fuecs->whereNotNull('anulado_at')->count(),
            'uso_flota' => (int) round(100 * $usados / $operando),
            'vehiculos_con_servicio' => $usados,
            'vehiculos_operando' => $operando,
            'proximos_sin_extracto' => Servicio::query()->deEmpresa($this->empresaId)->vigentes()->whereNull('fuec_id')
                ->entre(today(), today()->addDays(2))->get()
                ->filter(fn (Servicio $s) => $s->salida()->between(now(), now()->addHours(48)))->count(),
            'contratos_nuevos' => Contrato::query()->deEmpresa($this->empresaId)->noAnulados()
                ->when($this->oficinaId, fn ($q) => $q->where('oficina_id', $this->oficinaId))
                ->whereBetween('created_at', [$this->desde, $this->hasta])->count(),
            'clientes' => $clientes->all(),
        ];
    }

    // ── Comercial y cartera ───────────────────────────────────────────────

    public function cartera(): array
    {
        $contratado = (float) Contrato::query()->deEmpresa($this->empresaId)->noAnulados()
            ->when($this->oficinaId, fn ($q) => $q->where('oficina_id', $this->oficinaId))
            ->whereBetween('created_at', [$this->desde, $this->hasta])->sum('valor');

        $recaudado = (float) $this->pagos()->whereDate('fecha', '>=', $this->desde->toDateString())->whereDate('fecha', '<=', $this->hasta->toDateString())->sum('valor');
        $hoy = (float) $this->pagos()->whereDate('fecha', today())->sum('valor');

        // Saldos a hoy por edad (días vencidos desde la fecha límite de pago, o el fin del contrato).
        $edades = ['al_dia' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'd90' => 0.0];
        $conSaldo = 0;
        Contrato::query()->deEmpresa($this->empresaId)->noAnulados()
            ->when($this->oficinaId, fn ($q) => $q->where('oficina_id', $this->oficinaId))
            ->withSum(['pagos' => fn ($q) => $q->whereNull('anulado_at')], 'valor')
            ->get()
            ->each(function (Contrato $c) use (&$edades, &$conSaldo): void {
                $saldo = $c->saldo();
                if ($saldo <= 0) {
                    return;
                }
                $conSaldo++;
                $vence = $c->fecha_limite_pago ?? $c->fecha_fin;
                $dias = $vence ? (int) $vence->diffInDays(today(), false) : 0;
                $clave = match (true) {
                    $dias <= 0 => 'al_dia',
                    $dias <= 30 => 'd1_30',
                    $dias <= 60 => 'd31_60',
                    $dias <= 90 => 'd61_90',
                    default => 'd90',
                };
                $edades[$clave] += $saldo;
            });

        return [
            'contratado' => $contratado,
            'recaudado' => $recaudado,
            'recaudo_hoy' => $hoy,
            'cartera' => array_sum($edades),
            'cartera_vencida' => $edades['d1_30'] + $edades['d31_60'] + $edades['d61_90'] + $edades['d90'],
            'contratos_con_saldo' => $conSaldo,
            'edades' => $edades,
            'contratos_por_vencer' => Contrato::query()->deEmpresa($this->empresaId)->noAnulados()
                ->when($this->oficinaId, fn ($q) => $q->where('oficina_id', $this->oficinaId))
                ->whereBetween('fecha_fin', [today()->toDateString(), today()->addDays(15)->toDateString()])->count(),
        ];
    }

    // ── Seguridad vial ────────────────────────────────────────────────────

    public function pesv(): array
    {
        $servicios = $this->servicios();
        $detalle = $this->detallePreoperacional($this->desde, $this->corte);
        [$exigidos, $cumplidos] = [$detalle['exigidos'], $detalle['aptos']];
        $conExtracto = $servicios->whereNotNull('fuec_id');
        $pendientes = PlanAccion::query()->deEmpresa($this->empresaId)->pendientes();

        return [
            'preop_cumplimiento' => $exigidos ? (int) round(100 * $cumplidos / $exigidos) : null,
            'preop_exigidos' => $exigidos,
            'preop_detalle' => $detalle,
            'preop_cumplidos' => $cumplidos,
            'preop_no_aptos' => Inspeccion::query()->deEmpresa($this->empresaId)->preoperacionales()
                ->where('resultado', ResultadoInspeccion::NoApto->value)
                ->whereDate('fecha', '>=', $this->desde->toDateString())->whereDate('fecha', '<=', $this->hasta->toDateString())->count(),
            'planes_pendientes' => (clone $pendientes)->count(),
            'planes_vencidos' => (clone $pendientes)->whereNotNull('fecha_limite')->whereDate('fecha_limite', '<', today())->count(),
            'planes_alta' => (clone $pendientes)->where('prioridad', PrioridadPlan::Alta->value)->count(),
            'avisos_jornada' => $servicios->filter(fn (Servicio $s) => Jornada::avisos($s, $s->conductores->pluck('id')->all()) !== [])->count(),
            'planes_leidos' => $conExtracto->count() ? (int) round(100 * $conExtracto->whereNotNull('leido_at')->count() / $conExtracto->count()) : null,
            'novedades_ruta' => EventoServicio::query()->deEmpresa($this->empresaId)->where('tipo', 'novedad')
                ->whereBetween('created_at', [$this->desde, $this->hasta])->count(),
        ];
    }

    /**
     * Días-vehículo con extracto vigente (hasta hoy) frente a los que tuvieron preoperacional APTO ese día.
     *
     * @return array{0:int,1:int}
     */
    public function cumplimientoPreoperacional(Carbon $desde, Carbon $hasta): array
    {
        $d = $this->detallePreoperacional($desde, $hasta);

        return [$d['exigidos'], $d['aptos']];
    }

    /**
     * Días-vehículo con extracto vigente (hasta $hasta) según lo que pasó ese día:
     * preoperacional APTO, solo NO APTO, o sin preoperacional.
     *
     * @return array{exigidos:int, aptos:int, no_aptos:int, sin_hacer:int}
     */
    public function detallePreoperacional(Carbon $desde, Carbon $hasta): array
    {
        $vacio = ['exigidos' => 0, 'aptos' => 0, 'no_aptos' => 0, 'sin_hacer' => 0];
        if ($hasta->lt($desde)) {
            return $vacio;
        }

        $pares = [];
        Fuec::query()->deEmpresa($this->empresaId)->noAnulados()
            ->when($this->oficinaId, fn ($q) => $q->where('oficina_id', $this->oficinaId))
            ->whereDate('fecha_inicio', '<=', $hasta->toDateString())->whereDate('fecha_fin', '>=', $desde->toDateString())
            ->get(['vehiculo_id', 'fecha_inicio', 'fecha_fin'])
            ->each(function (Fuec $f) use (&$pares, $desde, $hasta): void {
                $d = $f->fecha_inicio->copy()->max($desde->copy()->startOfDay());
                $fin = $f->fecha_fin->copy()->min($hasta->copy()->startOfDay());
                for (; $d->lte($fin); $d->addDay()) {
                    $pares[$f->vehiculo_id.'|'.$d->toDateString()] = true;
                }
            });

        if (! $pares) {
            return $vacio;
        }

        $resultados = Inspeccion::query()->deEmpresa($this->empresaId)->preoperacionales()
            ->whereDate('fecha', '>=', $desde->toDateString())->whereDate('fecha', '<=', $hasta->toDateString())
            ->get(['vehiculo_id', 'fecha', 'resultado'])
            ->groupBy(fn (Inspeccion $i) => $i->vehiculo_id.'|'.$i->fecha->toDateString())
            ->map(fn ($g) => $g->contains(fn (Inspeccion $i) => $i->resultado === ResultadoInspeccion::Apto) ? 'apto' : 'no_apto');

        $aptos = $noAptos = 0;
        foreach (array_keys($pares) as $par) {
            match ($resultados[$par] ?? null) {
                'apto' => $aptos++,
                'no_apto' => $noAptos++,
                default => null,
            };
        }

        return ['exigidos' => count($pares), 'aptos' => $aptos, 'no_aptos' => $noAptos, 'sin_hacer' => count($pares) - $aptos - $noAptos];
    }

    // ── Mantenimiento ─────────────────────────────────────────────────────

    public function mantenimiento(): array
    {
        $programa = ProgramaAnual::programaVigente($this->empresaId, (int) $this->corte->year);
        $ejecutadas = 0;
        $exigibles = 0;
        if ($programa) {
            foreach (ProgramaAnual::indicadores($programa) as $t) {
                $ejecutadas += $t['ejecutadas'];
                $exigibles += $t['exigibles'];
            }
        }

        $reales = Mantenimiento::query()->deEmpresa($this->empresaId)->reales()
            ->whereDate('fecha', '>=', $this->desde->toDateString())->whereDate('fecha', '<=', $this->hasta->toDateString())
            ->with('vehiculo')->get();

        $porVehiculo = $reales->groupBy('vehiculo_id')->map(function (Collection $m) {
            $vehiculo = $m->first()->vehiculo;
            $km = $vehiculo ? $this->kmRecorridos($vehiculo->id) : null;
            $costo = (float) $m->sum('valor');

            return [
                'placa' => $vehiculo?->placa ?? '—',
                'mantenimientos' => $m->count(),
                'costo' => $costo,
                'km' => $km,
                'costo_km' => $km ? round($costo / $km) : null,
            ];
        })->sortByDesc('costo')->values();

        $kmFlota = $porVehiculo->sum(fn ($v) => $v['km'] ?? 0);
        $costo = (float) $reales->sum('valor');

        return [
            'programa' => $programa?->nombre,
            'programa_cumplimiento' => $exigibles ? (int) round(100 * $ejecutadas / $exigibles) : null,
            'programa_ejecutadas' => $ejecutadas,
            'programa_exigibles' => $exigibles,
            'actividades_vencidas' => $programa ? ActividadMantenimiento::query()->where('programa_mantenimiento_id', $programa->id)
                ->where('estado', EstadoActividad::Programada->value)->whereDate('fecha_programada', '<', today())->count() : 0,
            'preventivos' => $reales->where('tipo', TipoMantenimiento::Preventivo)->count(),
            'correctivos' => $reales->where('tipo', TipoMantenimiento::Correctivo)->count(),
            'costo' => $costo,
            'costo_km' => $kmFlota ? round($costo / $kmFlota) : null,
            'por_vehiculo' => $porVehiculo->take(10)->all(),
        ];
    }

    /** Km recorridos por un vehículo en el periodo (lecturas de preoperacionales, inspecciones y mantenimientos). */
    public function kmRecorridos(int $vehiculoId): ?int
    {
        $rango = [$this->desde->toDateString(), $this->hasta->toDateString()];
        $lecturas = Inspeccion::query()->deEmpresa($this->empresaId)->where('vehiculo_id', $vehiculoId)->whereNotNull('kilometraje')
            ->whereDate('fecha', '>=', $rango[0])->whereDate('fecha', '<=', $rango[1])->pluck('kilometraje')
            ->merge(Mantenimiento::query()->deEmpresa($this->empresaId)->vigentes()->where('vehiculo_id', $vehiculoId)
                ->whereNotNull('kilometraje')->whereDate('fecha', '>=', $rango[0])->whereDate('fecha', '<=', $rango[1])->pluck('kilometraje'));

        $km = $lecturas->count() >= 2 ? (int) ($lecturas->max() - $lecturas->min()) : 0;

        return $km > 0 ? $km : null;
    }

    // ── Tendencias (12 meses que terminan en el mes del periodo) ──────────

    /** @return array{meses: list<string>, recaudo: list<float>, extractos: list<int>, preoperacional: list<?int>, costo_mantenimiento: list<float>} */
    public function tendencias(): array
    {
        $fin = $this->hasta->copy()->startOfMonth();
        $meses = collect(range(11, 0))->map(fn ($i) => $fin->copy()->subMonthsNoOverflow($i));
        $desde = $meses->first()->copy()->startOfMonth();
        $hasta = $fin->copy()->endOfMonth();

        $clave = fn ($fecha) => Carbon::parse($fecha)->format('Y-m');

        $recaudo = $this->pagos()->whereDate('fecha', '>=', $desde->toDateString())->whereDate('fecha', '<=', $hasta->toDateString())->get(['fecha', 'valor'])
            ->groupBy(fn ($p) => $clave($p->fecha))->map(fn ($g) => (float) $g->sum('valor'));
        $extractos = Fuec::query()->deEmpresa($this->empresaId)->noAnulados()
            ->when($this->oficinaId, fn ($q) => $q->where('oficina_id', $this->oficinaId))
            ->whereBetween('created_at', [$desde, $hasta])->get(['created_at'])
            ->groupBy(fn ($f) => $clave($f->created_at))->map->count();
        $costo = Mantenimiento::query()->deEmpresa($this->empresaId)->reales()->whereDate('fecha', '>=', $desde->toDateString())->whereDate('fecha', '<=', $hasta->toDateString())
            ->get(['fecha', 'valor'])->groupBy(fn ($m) => $clave($m->fecha))->map(fn ($g) => (float) $g->sum('valor'));

        return [
            'meses' => $meses->map(fn (Carbon $m) => ucfirst($m->translatedFormat('M y')))->all(),
            'recaudo' => $meses->map(fn (Carbon $m) => $recaudo[$m->format('Y-m')] ?? 0.0)->all(),
            'extractos' => $meses->map(fn (Carbon $m) => (int) ($extractos[$m->format('Y-m')] ?? 0))->all(),
            'preoperacional' => $meses->map(function (Carbon $m) {
                if ($m->gt(today())) {
                    return null;
                }
                [$e, $c] = $this->cumplimientoPreoperacional($m->copy()->startOfMonth(), $m->copy()->endOfMonth()->min(today()));

                return $e ? (int) round(100 * $c / $e) : null;
            })->all(),
            'costo_mantenimiento' => $meses->map(fn (Carbon $m) => $costo[$m->format('Y-m')] ?? 0.0)->all(),
        ];
    }

    // ── Datos para gráficas ───────────────────────────────────────────────

    /** Pagos del periodo por tipo de recibo: abono, abono final (deja el saldo en cero) y pago total. */
    public function pagosPorTipo(): array
    {
        $tipos = ['abono' => ['n' => 0, 'valor' => 0.0], 'abono_final' => ['n' => 0, 'valor' => 0.0], 'total' => ['n' => 0, 'valor' => 0.0]];
        $this->pagos()->whereDate('fecha', '>=', $this->desde->toDateString())->whereDate('fecha', '<=', $this->hasta->toDateString())
            ->with('contrato')->get()
            ->each(function (PagoContrato $p) use (&$tipos): void {
                $tipos[$p->tipo()]['n']++;
                $tipos[$p->tipo()]['valor'] += (float) $p->valor;
            });

        return $tipos;
    }

    /**
     * Recaudo y extractos expedidos a lo largo del periodo: por día si son hasta 62 días, si no por mes.
     *
     * @return array{etiquetas: list<string>, recaudo: list<float>, extractos: list<int>}
     */
    public function serie(): array
    {
        $porDia = $this->desde->diffInDays($this->hasta) <= 62;
        $clave = fn ($f) => Carbon::parse($f)->format($porDia ? 'Y-m-d' : 'Y-m');
        $puntos = [];
        for ($d = $this->desde->copy(); $d->lte($this->hasta); $porDia ? $d->addDay() : $d->addMonthNoOverflow()->startOfMonth()) {
            $puntos[$d->format($porDia ? 'Y-m-d' : 'Y-m')] = $porDia ? $d->format('d/m') : ucfirst($d->translatedFormat('M y'));
        }

        $recaudo = $this->pagos()->whereDate('fecha', '>=', $this->desde->toDateString())->whereDate('fecha', '<=', $this->hasta->toDateString())
            ->get(['fecha', 'valor'])->groupBy(fn ($p) => $clave($p->fecha))->map(fn ($g) => (float) $g->sum('valor'));
        $extractos = Fuec::query()->deEmpresa($this->empresaId)->noAnulados()
            ->when($this->oficinaId, fn ($q) => $q->where('oficina_id', $this->oficinaId))
            ->whereBetween('created_at', [$this->desde, $this->hasta])->get(['created_at'])
            ->groupBy(fn ($f) => $clave($f->created_at->copy()->timezone(config('app.timezone'))))->map->count();

        return [
            'etiquetas' => array_values($puntos),
            'recaudo' => array_map(fn ($k) => $recaudo[$k] ?? 0.0, array_keys($puntos)),
            'extractos' => array_map(fn ($k) => (int) ($extractos[$k] ?? 0), array_keys($puntos)),
        ];
    }

    /** Saldo por cobrar a hoy de los 8 clientes que más deben. */
    public function carteraPorCliente(): array
    {
        return Contrato::query()->deEmpresa($this->empresaId)->noAnulados()
            ->when($this->oficinaId, fn ($q) => $q->where('oficina_id', $this->oficinaId))
            ->withSum(['pagos' => fn ($q) => $q->whereNull('anulado_at')], 'valor')->with('cliente')->get()
            ->filter(fn (Contrato $c) => $c->saldo() > 0)
            ->groupBy('cliente_id')
            ->map(fn ($g) => ['cliente' => Str::limit($g->first()->cliente?->nombre ?? '—', 28), 'saldo' => (float) $g->sum(fn (Contrato $c) => $c->saldo())])
            ->sortByDesc('saldo')->take(8)->values()->all();
    }

    /** Días de servicio por vehículo en el periodo (los 10 más usados). */
    public function serviciosPorVehiculo(): array
    {
        $dias = [];
        foreach ($this->servicios()->whereNotNull('vehiculo_id') as $s) {
            $inicio = $s->fecha_inicio->copy()->max($this->desde->copy()->startOfDay());
            $fin = $s->fecha_fin->copy()->min($this->hasta->copy()->startOfDay());
            $dias[$s->vehiculo_id] = ($dias[$s->vehiculo_id] ?? 0) + max(0, (int) $inicio->diffInDays($fin) + 1);
        }
        arsort($dias);
        $placas = Vehiculo::query()->deEmpresa($this->empresaId)->withTrashed()->whereKey(array_keys($dias))->pluck('placa', 'id');

        return collect(array_slice($dias, 0, 10, true))->map(fn ($n, $id) => ['placa' => $placas[$id] ?? '—', 'dias' => $n])->values()->all();
    }

    // ── Consultas base ────────────────────────────────────────────────────

    /** @return Collection<int, Servicio> */
    private function servicios(): Collection
    {
        return once(fn () => Servicio::query()->deEmpresa($this->empresaId)->vigentes()->entre($this->desde, $this->hasta)
            ->when($this->oficinaId, fn ($q) => $q->where(fn ($q) => $q->whereNull('fuec_id')->orWhereHas('fuec', fn ($f) => $f->where('oficina_id', $this->oficinaId))))
            ->with(['conductores', 'ruta'])->get());
    }

    /** @return Collection<int, Fuec> */
    private function fuecs(): Collection
    {
        return Fuec::query()->deEmpresa($this->empresaId)
            ->when($this->oficinaId, fn ($q) => $q->where('oficina_id', $this->oficinaId))
            ->whereBetween('created_at', [$this->desde, $this->hasta])->get(['id', 'anulado_at']);
    }

    private function pagos(): Builder
    {
        return PagoContrato::query()->deEmpresa($this->empresaId)->vigentes()
            ->when($this->oficinaId, fn ($q) => $q->whereHas('contrato', fn ($c) => $c->where('oficina_id', $this->oficinaId)));
    }

    /** "$ 1.234.567" */
    public static function pesos(float|int|null $valor): string
    {
        return '$ '.number_format((float) $valor, 0, ',', '.');
    }
}
