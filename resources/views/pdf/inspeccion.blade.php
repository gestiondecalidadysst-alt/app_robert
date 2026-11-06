{{-- Preoperacional o inspección mensual. La firma es la copia de la firma registrada del conductor. --}}
@php
    $e = $d['empresa'] ?? [];
    $v = $d['vehiculo'] ?? [];
    $c = $d['conductor'] ?? null;
    $marca = ['si' => 'Cumple', 'no' => 'No cumple', 'na' => 'N/A'];
    $fallas = $i->fallas();
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $i->tipo->corto() }} {{ $v['placa'] ?? '' }}</title>
<style>
    @page { margin: 34px 38px 40px 38px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.6px; color: #111; }
    table { width: 100%; border-collapse: collapse; }
    .enc td { vertical-align: middle; }
    .logo { max-height: 54px; max-width: 130px; }
    .titulo { text-align: center; font-weight: bold; font-size: 11px; }
    .sub { text-align: center; font-size: 8px; color: #333; }
    .caja td, .caja th { border: 1px solid #9aa5b1; padding: 4px 5px; text-align: left; vertical-align: top; }
    .caja th { background: #eef1f5; font-weight: bold; width: 17%; }
    .items td, .items th { border: 1px solid #9aa5b1; padding: 3px 5px; }
    .items th { background: #1B2B3A; color: #fff; font-size: 8px; text-align: left; }
    .grupo td { background: #eef1f5; font-weight: bold; }
    .c { text-align: center; width: 52px; }
    .no { color: #b91c1c; font-weight: bold; }
    .crit { color: #b91c1c; font-size: 7px; font-weight: bold; }
    .res { display: inline-block; padding: 3px 10px; border-radius: 10px; color: #fff; font-weight: bold; font-size: 9.5px; }
    .apto { background: #15803d; } .noapto { background: #b91c1c; }
    .firma td { vertical-align: bottom; }
    .firma-img { height: 54px; max-width: 200px; }
    .linea { border-top: 1px solid #111; padding-top: 3px; font-size: 8px; }
    .legal { font-size: 7.2px; color: #444; }
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 7px; }
</style>
</head>
<body>
<table class="enc">
    <tr>
        <td style="width: 24%">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
        <td style="width: 52%">
            <div class="titulo">{{ mb_strtoupper($e['razon_social'] ?? '') }}</div>
            <div class="sub">NIT {{ $e['nit'] ?? '' }}</div>
            <div class="titulo" style="margin-top: 6px">{{ $i->tipo->value === 'mensual' ? 'INSPECCIÓN MENSUAL DEL VEHÍCULO' : 'INSPECCIÓN PREOPERACIONAL (ALISTAMIENTO DIARIO)' }}</div>
            <div class="sub">Plan Estratégico de Seguridad Vial</div>
        </td>
        <td style="width: 24%; text-align: right">
            <span class="res {{ $i->apta() ? 'apto' : 'noapto' }}">{{ mb_strtoupper($i->resultado->getLabel()) }}</span>
        </td>
    </tr>
</table>

<table class="caja" style="margin-top: 10px">
    <tr>
        <th>Fecha y hora</th><td>{{ $i->created_at->format('d/m/Y h:i a') }}</td>
        <th>Placa / No. interno</th><td>{{ $v['placa'] ?? '' }}{{ ! empty($v['numero_interno']) ? ' / '.$v['numero_interno'] : '' }}</td>
    </tr>
    <tr>
        <th>Vehículo</th><td>{{ trim(($v['clase'] ?? '').' '.($v['marca'] ?? '').' '.($v['linea'] ?? '').' '.($v['modelo'] ?? '')) }}</td>
        <th>Kilometraje</th><td>{{ $i->kilometraje !== null ? number_format($i->kilometraje, 0, ',', '.') : '—' }}</td>
    </tr>
    <tr>
        <th>Conductor</th><td>{{ $c['nombre'] ?? '—' }}{{ ! empty($c['documento']) ? ' · C.C. '.$c['documento'] : '' }}</td>
        <th>Licencia</th><td>{{ ! empty($c['licencia']) ? $c['licencia'].(! empty($c['categoria']) ? ' ('.$c['categoria'].')' : '') : '—' }}</td>
    </tr>
    @if (! empty($d['fuec']))
        <tr><th>Extracto (FUEC)</th><td colspan="3">{{ $i->numeroExtracto() ?? $d['fuec']['numero'] }}{{ $i->numeroContrato() ? ' · contrato '.$i->numeroContrato() : (! empty($d['fuec']['contrato']) ? ' · contrato '.$d['fuec']['contrato'] : '') }}</td></tr>
    @endif
</table>

<table class="items" style="margin-top: 10px">
    <tr><th>Ítem</th><th class="c">Cumple</th><th class="c">No cumple</th><th class="c">N/A</th><th style="width: 34%">Observación</th></tr>
    @foreach ($grupos as $grupo => $respuestas)
        <tr class="grupo"><td colspan="5">{{ $grupo }}</td></tr>
        @foreach ($respuestas as $r)
            <tr>
                <td>{{ $r['descripcion'] }}@if ($r['critico']) <span class="crit">CRÍTICO</span>@endif</td>
                <td class="c">{{ $r['respuesta'] === 'si' ? 'X' : '' }}</td>
                <td class="c no">{{ $r['respuesta'] === 'no' ? 'X' : '' }}</td>
                <td class="c">{{ $r['respuesta'] === 'na' ? 'X' : '' }}</td>
                <td>{{ $r['observacion'] }}</td>
            </tr>
        @endforeach
    @endforeach
</table>

<table class="caja" style="margin-top: 10px">
    <tr><th>Resultado</th><td>
        {{ count($i->respuestas) }} ítems revisados · {{ count($fallas) }} no cumple{{ count($fallas) === 1 ? '' : 'n' }}.
        @if (! $i->apta()) El vehículo <strong>no debe operar</strong> hasta corregir los ítems críticos. @endif
        @if (! empty($d['plan_pendiente'])) Tenía pendiente el plan de acción {{ $d['plan_pendiente'] }} (falla crítica sin cerrar). @endif
    </td></tr>
    <tr><th>Observaciones</th><td>{{ $i->observaciones ?: '—' }}</td></tr>
</table>

<table class="firma" style="margin-top: 16px">
    <tr>
        <td style="width: 46%">
            @if ($firma)<img class="firma-img" src="{{ $firma }}" alt="">@else<div style="height: 54px"></div>@endif
            <div class="linea">
                <strong>{{ $c['nombre'] ?? ($d['registrado_por'] ?? '') }}</strong>{{ ! empty($c['documento']) ? ' · C.C. '.$c['documento'] : '' }}<br>
                {{ $c ? 'Conductor' : 'Inspector' }}
            </div>
        </td>
        <td style="width: 8%"></td>
        <td style="width: 46%" class="legal">
            @if (! empty($d['registrado_por_conductor']))
                Firmado electrónicamente por el conductor el {{ $i->created_at->format('d/m/Y \a \l\a\s h:i a') }}, al registrar la inspección con su usuario personal del portal del conductor.
                La firma es la que registró en su hoja de vida.
            @else
                Registrado por {{ $d['registrado_por'] ?? 'la empresa' }} el {{ $i->created_at->format('d/m/Y h:i a') }}.
            @endif
            @if ($i->ip) IP {{ $i->ip }}.@endif
            @if ($i->latitud !== null) Ubicación {{ number_format($i->latitud, 5) }}, {{ number_format($i->longitud, 5) }}.@endif
            <table style="margin-top: 6px"><tr>
                <td style="border: none" class="mono">Verificación:<br>{{ $i->urlVerificacion() }}</td>
                <td style="border: none; width: 62px; text-align: right"><img src="{{ $qr }}" style="width: 58px; height: 58px" alt="QR"></td>
            </tr></table>
        </td>
    </tr>
</table>
</body>
</html>
