{{-- Resumen de gerencia: los indicadores del tablero para un periodo. Lo arma App\Support\Gerencia\InformeGerencia. --}}
@php
    $p = fn ($v) => \App\Support\Gerencia\Indicadores::pesos($v);
    $pct = fn ($v) => $v === null ? '—' : $v . ' %';
    $f = $flota; $o = $operacion; $c = $cartera; $s = $pesv; $m = $mantenimiento;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Resumen de gerencia</title>
<style>
    @page { margin: 34px 40px 38px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.8px; color: #111; line-height: 1.4; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .logo { max-height: 50px; max-width: 130px; }
    h1 { font-size: 13px; margin: 0; }
    h2 { font-size: 9.8px; margin: 14px 0 5px; padding: 3px 6px; background: #1B2B3A; color: #fff; }
    .kpis td { width: 25%; border: 1px solid #c9d1da; padding: 5px 7px; }
    .kpis small { display: block; color: #555; font-size: 7.6px; }
    .kpis strong { font-size: 12.5px; }
    .kpis .det { color: #555; font-size: 7.4px; }
    .tabla th { background: #eef1f5; border: 1px solid #9aa5b1; padding: 3px 5px; text-align: left; font-size: 8px; }
    .tabla td { border: 1px solid #c9d1da; padding: 3px 5px; }
    .der { text-align: right; }
    .tenue { color: #555; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 22%">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
        <td style="padding-left: 6px">
            <div style="font-weight: bold; font-size: 10.5px">{{ mb_strtoupper($empresa->razon_social) }}</div>
            <div class="tenue">{{ collect([$empresa->direccion, $empresa->municipio?->nombre])->filter()->implode(' · ') }}</div>
        </td>
        <td style="width: 34%; text-align: right">
            <h1>RESUMEN DE GERENCIA</h1>
            <div><strong>{{ $i->etiquetaPeriodo() }}</strong>{{ $oficina ? ' · Oficina ' . $oficina : '' }}</div>
            <div class="tenue">Generado el {{ now()->format('d/m/Y h:i a') }}</div>
        </td>
    </tr>
</table>

<h2>Flota y conductores (a la fecha de generación)</h2>
<table class="kpis"><tr>
    <td><small>Vehículos operando</small><strong>{{ $f['operando'] }} de {{ $f['vehiculos'] }}</strong><div class="det">{{ $f['suspendidos'] }} suspendidos · {{ $f['taller'] }} en taller</div></td>
    <td><small>Sin poder operar por documentos</small><strong>{{ $f['sin_documentos'] }}</strong></td>
    <td><small>Documentos de vehículos</small><strong>{{ $f['docs_vencidos'] }} vencidos</strong><div class="det">{{ $f['docs_por_vencer'] }} por vencer</div></td>
    <td><small>Conductores activos</small><strong>{{ $f['conductores'] }}</strong><div class="det">{{ $f['licencias_vencidas'] }} con documentos vencidos · {{ $f['licencias_por_vencer'] }} por vencer</div></td>
</tr></table>

<h2>Operación</h2>
<table class="kpis"><tr>
    <td><small>Extractos expedidos</small><strong>{{ $o['extractos'] }}</strong><div class="det">{{ $o['extractos_anulados'] }} anulados · {{ $o['contratos_nuevos'] }} contratos nuevos</div></td>
    <td><small>Servicios</small><strong>{{ $o['servicios'] }}</strong><div class="det">{{ $o['servicios_con_extracto'] }} con extracto · {{ $o['servicios_sin_asignar'] }} sin asignar</div></td>
    <td><small>Uso de la flota</small><strong>{{ $o['uso_flota'] }} %</strong><div class="det">{{ $o['vehiculos_con_servicio'] }} de {{ $o['vehiculos_operando'] }} vehículos</div></td>
    <td><small>Salen en 48 h sin extracto</small><strong>{{ $o['proximos_sin_extracto'] }}</strong></td>
</tr></table>
@if ($o['clientes'])
    <table class="tabla" style="margin-top: 5px">
        <tr><th>Principales clientes (contratos nuevos)</th><th class="der" style="width: 14%">Contratos</th><th class="der" style="width: 20%">Valor</th></tr>
        @foreach ($o['clientes'] as $cl)<tr><td>{{ $cl['cliente'] }}</td><td class="der">{{ $cl['contratos'] }}</td><td class="der">{{ $p($cl['valor']) }}</td></tr>@endforeach
    </table>
@endif

<h2>Comercial y cartera de contratos</h2>
<table class="kpis"><tr>
    <td><small>Contratado en el periodo</small><strong>{{ $p($c['contratado']) }}</strong></td>
    <td><small>Recaudado en el periodo</small><strong>{{ $p($c['recaudado']) }}</strong></td>
    <td><small>Cartera por cobrar</small><strong>{{ $p($c['cartera']) }}</strong><div class="det">{{ $c['contratos_con_saldo'] }} contratos con saldo</div></td>
    <td><small>Cartera vencida</small><strong>{{ $p($c['cartera_vencida']) }}</strong><div class="det">{{ $c['contratos_por_vencer'] }} contratos terminan en 15 días</div></td>
</tr></table>
<table class="tabla" style="margin-top: 5px">
    <tr><th>Sin vencer</th><th>1–30 días</th><th>31–60 días</th><th>61–90 días</th><th>Más de 90 días</th></tr>
    <tr>@foreach ($c['edades'] as $v)<td class="der">{{ $p($v) }}</td>@endforeach</tr>
</table>

<h2>Seguridad vial</h2>
<table class="kpis"><tr>
    <td><small>Preoperacional cumplido</small><strong>{{ $pct($s['preop_cumplimiento']) }}</strong><div class="det">{{ $s['preop_cumplidos'] }} de {{ $s['preop_exigidos'] }} días con extracto · {{ $s['preop_no_aptos'] }} no aptos</div></td>
    <td><small>Planes de acción pendientes</small><strong>{{ $s['planes_pendientes'] }}</strong><div class="det">{{ $s['planes_vencidos'] }} vencidos · {{ $s['planes_alta'] }} prioridad alta</div></td>
    <td><small>Servicios con avisos de jornada</small><strong>{{ $s['avisos_jornada'] }}</strong></td>
    <td><small>Planes de viaje leídos</small><strong>{{ $pct($s['planes_leidos']) }}</strong><div class="det">{{ $s['novedades_ruta'] }} novedades en ruta</div></td>
</tr></table>

<h2>Mantenimiento</h2>
<table class="kpis"><tr>
    <td><small>Cumplimiento del programa (a la fecha)</small><strong>{{ $pct($m['programa_cumplimiento']) }}</strong><div class="det">{{ $m['programa'] ?? 'Sin programa del año' }}</div></td>
    <td><small>Actividades vencidas</small><strong>{{ $m['actividades_vencidas'] }}</strong></td>
    <td><small>Preventivos / correctivos</small><strong>{{ $m['preventivos'] }} / {{ $m['correctivos'] }}</strong></td>
    <td><small>Costo de mantenimiento</small><strong>{{ $p($m['costo']) }}</strong><div class="det">{{ $m['costo_km'] ? $p($m['costo_km']) . ' por km' : 'Sin km suficientes' }}</div></td>
</tr></table>
@if ($m['por_vehiculo'])
    <table class="tabla" style="margin-top: 5px">
        <tr><th>Vehículo</th><th class="der">Mantenimientos</th><th class="der">Costo</th><th class="der">Km recorridos</th><th class="der">Costo por km</th></tr>
        @foreach ($m['por_vehiculo'] as $v)
            <tr><td>{{ $v['placa'] }}</td><td class="der">{{ $v['mantenimientos'] }}</td><td class="der">{{ $p($v['costo']) }}</td><td class="der">{{ $v['km'] ? number_format($v['km'], 0, ',', '.') : '—' }}</td><td class="der">{{ $v['costo_km'] ? $p($v['costo_km']) : '—' }}</td></tr>
        @endforeach
    </table>
@endif
</body>
</html>
