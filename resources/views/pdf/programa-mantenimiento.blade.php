{{-- Cronograma anual de mantenimiento preventivo. Por actividad: P = programada (mes de la línea base); en el mes real: E ejecutada, R reprogramada, V vencida, C cancelada. --}}
@php
    $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $tot = ['programadas' => $indicadores->sum('programadas'), 'ejecutadas' => $indicadores->sum('ejecutadas'), 'exigibles' => $indicadores->sum('exigibles')];
    $tot['cumplimiento'] = $tot['exigibles'] ? round($tot['ejecutadas'] * 100 / $tot['exigibles'], 1) : null;
    $celdas = function ($actividades) {
        // [mes => [marcas]] por rutina
        $m = [];
        foreach ($actividades as $a) {
            $situacion = $a->situacion(0);
            $mesBase = $a->fecha_original->month;
            $mesReal = match (true) {
                $a->estado->value === 'ejecutada' => $a->fecha_ejecucion?->year === $a->programa?->anio ? $a->fecha_ejecucion->month : $mesBase,
                default => $a->fecha_programada->month,
            };
            $m[$mesBase][] = 'P';
            $marca = match (true) {
                $a->estado->value === 'ejecutada' => 'E',
                $a->estado->value === 'cancelada' => 'C',
                $situacion === 'vencida' => 'V',
                $mesReal !== $mesBase => 'R',
                default => null,
            };
            if ($marca) {
                $m[$mesReal][] = $marca;
            }
        }

        return $m;
    };
    $color = ['P' => '#1d4ed8', 'E' => '#15803d', 'R' => '#b45309', 'V' => '#b91c1c', 'C' => '#64748b'];
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Programa de mantenimiento {{ $programa->anio }}</title>
<style>
    @page { margin: 24px 24px 30px 24px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #111; }
    table { width: 100%; border-collapse: collapse; }
    .enc td { border: 1px solid #9aa5b1; padding: 3px 5px; vertical-align: middle; }
    .logo { max-height: 40px; max-width: 110px; }
    .titulo { text-align: center; font-weight: bold; font-size: 10px; }
    .m td, .m th { border: 1px solid #9aa5b1; padding: 2px 3px; text-align: center; }
    .m th { background: #1B2B3A; color: #fff; font-size: 6.8px; }
    .m td.r { text-align: left; }
    .veh td { background: #eef1f5; font-weight: bold; text-align: left; font-size: 7.4px; }
    .mk { font-weight: bold; }
    .firmas td { width: 33%; padding: 18px 14px 0; vertical-align: bottom; font-size: 7px; }
    .linea { border-top: 1px solid #111; padding-top: 2px; }
</style>
</head>
<body>
<table class="enc">
    <tr>
        <td rowspan="2" style="width: 16%; text-align: center">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
        <td style="width: 60%" class="titulo">{{ mb_strtoupper($e['razon_social']) }} · NIT {{ $e['nit'] }}</td>
        <td style="width: 24%">Código: {{ $programa->codigo_formato ?: '—' }}</td>
    </tr>
    <tr>
        <td class="titulo">PROGRAMA ANUAL DE MANTENIMIENTO PREVENTIVO {{ $programa->anio }}{{ $vehiculo ? ' · VEHÍCULO '.$vehiculo->placa : '' }}<br><span style="font-weight: normal; font-size: 7.4px">Plan Estratégico de Seguridad Vial · Mantenimiento y control de vehículos seguros</span></td>
        <td>Versión: {{ $programa->version }} · Estado: {{ $programa->estado->getLabel() }}<br>Aprobado: {{ $programa->aprobado_at?->format('d/m/Y') ?? 'pendiente' }}</td>
    </tr>
</table>

<table class="m" style="margin-top: 6px">
    <tr>
        <th style="width: 21%; text-align: left">Rutina</th>
        <th style="width: 9%">Frecuencia</th>
        @foreach ($meses as $mes)<th>{{ $mes }}</th>@endforeach
        <th style="width: 5%">Prog.</th>
        <th style="width: 5%">Ejec.</th>
    </tr>
    @forelse ($porVehiculo as $vehiculoId => $actividades)
        @php $v = $actividades->first()->vehiculo; @endphp
        <tr class="veh"><td colspan="16">{{ $v?->placa }}{{ $v?->numero_interno ? ' · No. interno '.$v->numero_interno : '' }} · {{ trim(($v?->claseVehiculo?->nombre ?? '').' '.($v?->marca?->nombre ?? '').' '.($v?->linea?->nombre ?? '').' '.($v?->modelo ?? '')) }}</td></tr>
        @foreach ($actividades->groupBy('rutina_nombre') as $rutina => $grupo)
            @php $c = $celdas($grupo); @endphp
            <tr>
                <td class="r">{{ $rutina }}</td>
                <td>{{ $grupo->first()->frecuencia }}</td>
                @for ($mes = 1; $mes <= 12; $mes++)
                    <td>@foreach ($c[$mes] ?? [] as $marca)<span class="mk" style="color: {{ $color[$marca] }}">{{ $marca }}</span> @endforeach</td>
                @endfor
                <td>{{ $grupo->where('estado.value', '!=', 'cancelada')->count() }}</td>
                <td>{{ $grupo->where('estado.value', 'ejecutada')->count() }}</td>
            </tr>
        @endforeach
    @empty
        <tr><td colspan="16" style="padding: 10px">El programa no tiene actividades.</td></tr>
    @endforelse
</table>
<p style="margin: 3px 0 0; font-size: 6.6px">
    <b style="color: {{ $color['P'] }}">P</b> programada (línea base) · <b style="color: {{ $color['E'] }}">E</b> ejecutada · <b style="color: {{ $color['R'] }}">R</b> reprogramada ·
    <b style="color: {{ $color['V'] }}">V</b> vencida sin ejecutar · <b style="color: {{ $color['C'] }}">C</b> cancelada. Las fechas se estiman con el kilometraje diario de cada vehículo (km o meses, lo que ocurra primero).
    Cada actividad conserva su historial (ejecución con evidencia, reprogramaciones y cancelaciones con motivo) en el sistema.
</p>

<table class="m" style="margin-top: 8px; width: 60%">
    <tr><th style="text-align: left">Indicador de cumplimiento</th><th>T1</th><th>T2</th><th>T3</th><th>T4</th><th>Año</th></tr>
    <tr><td class="r">Actividades de mantenimiento preventivo programadas</td>@foreach ($indicadores as $i)<td>{{ $i['programadas'] }}</td>@endforeach<td><b>{{ $tot['programadas'] }}</b></td></tr>
    <tr><td class="r">Actividades ejecutadas</td>@foreach ($indicadores as $i)<td>{{ $i['ejecutadas'] }}</td>@endforeach<td><b>{{ $tot['ejecutadas'] }}</b></td></tr>
    <tr><td class="r">% de cumplimiento a la fecha (ejecutadas / programadas ya vencidas)</td>@foreach ($indicadores as $i)<td>{{ $i['cumplimiento'] !== null ? $i['cumplimiento'].' %' : '—' }}</td>@endforeach<td><b>{{ $tot['cumplimiento'] !== null ? $tot['cumplimiento'].' %' : '—' }}</b></td></tr>
</table>

<table class="firmas" style="margin-top: 6px">
    <tr>
        <td><div class="linea"><b>Elaboró:</b> {{ $programa->elaboradoPor?->name ?? '' }}<br>Fecha: {{ $programa->elaborado_at?->format('d/m/Y') }}</div></td>
        <td><div class="linea"><b>Aprobó:</b> {{ $programa->aprobadoPor?->name ?? '' }}<br>Fecha: {{ $programa->aprobado_at?->format('d/m/Y') }}</div></td>
        <td style="text-align: right; color: #555">Generado el {{ now()->format('d/m/Y h:i a') }}{{ $programa->observaciones ? ' · '.$programa->observaciones : '' }}</td>
    </tr>
</table>
</body>
</html>
