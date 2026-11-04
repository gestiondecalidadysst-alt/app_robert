{{-- Constancia de una consulta de tránsito (SIMIT o RUNT). La arma App\Support\Pdf\PdfConsultaTransito. --}}
@php
    $pesos = fn ($n) => '$ ' . number_format((float) $n, 0, ',', '.');
    $fecha = fn ($f) => $f ? \Illuminate\Support\Carbon::parse($f)->format('d/m/Y') : '—';
    $momento = $c->created_at->timezone(config('app.timezone'));
    $fuente = $d['clase'] === 'comparendos' ? 'SIMIT' : 'RUNT';
    $titulo = match ($d['clase']) {
        'comparendos' => $d['paz_y_salvo'] ? 'CONSTANCIA DE CONSULTA · SIN COMPARENDOS NI MULTAS PENDIENTES' : 'CONSTANCIA DE CONSULTA · COMPARENDOS Y MULTAS',
        'licencia' => 'CONSTANCIA DE CONSULTA · LICENCIA DE CONDUCCIÓN',
        default => 'CONSTANCIA DE CONSULTA · REGISTRO DEL VEHÍCULO EN EL RUNT',
    };
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $titulo }}</title>
<style>
    @page { margin: 38px 46px 46px 46px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.2px; color: #111; line-height: 1.45; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .logo { max-height: 60px; max-width: 150px; }
    .tenue { color: #555; }
    h1 { text-align: center; font-size: 12px; margin: 16px 0 3px; letter-spacing: .3px; }
    .sub { text-align: center; color: #555; margin-bottom: 12px; }
    .datos td { padding: 4px 7px; border: 1px solid #c9d1da; }
    .datos .et { width: 24%; background: #eef1f5; font-weight: bold; }
    .resultado { margin: 14px 0; padding: 10px 12px; border: 2px solid; font-size: 11px; font-weight: bold; text-align: center; }
    .bien { border-color: #15803d; color: #14532d; background: #f0fdf4; }
    .mal { border-color: #b91c1c; color: #7f1d1d; background: #fef2f2; }
    .neutro { border-color: #475569; color: #1e293b; background: #f1f5f9; }
    .lista th, .lista td { border: 1px solid #9aa5b1; padding: 4px 5px; }
    .lista th { background: #1B2B3A; color: #fff; font-size: 8px; text-align: left; }
    .lista td { font-size: 8.4px; }
    .der { text-align: right; }
    .total td { font-weight: bold; background: #eef1f5; }
    .nota { margin-top: 16px; font-size: 7.8px; color: #555; border-top: 1px solid #c9d1da; padding-top: 6px; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 24%">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
        <td style="padding-left: 6px">
            <div style="font-weight: bold; font-size: 11px">{{ mb_strtoupper($empresa->razon_social) }}</div>
            <div class="tenue">NIT {{ $empresa->nit }}{{ $empresa->dv !== null ? '-' . $empresa->dv : '' }}</div>
            <div class="tenue">{{ collect([$empresa->direccion, $empresa->municipio?->nombre])->filter()->implode(' · ') }}</div>
        </td>
        <td style="width: 22%; text-align: right"><img src="{{ $qr }}" alt="" style="width: 78px"><div class="tenue" style="font-size: 7px">Verifique esta constancia</div></td>
    </tr>
</table>

<h1>{{ $titulo }}</h1>
<div class="sub">Consulta No. {{ $c->id }} · {{ $momento->format('d/m/Y') }} a las {{ $momento->format('h:i a') }} · Fuente: {{ $fuente }}</div>

<table class="datos">
    @foreach ($sujeto as [$et, $valor])
        <tr><td class="et">{{ $et }}</td><td>{{ $valor }}</td></tr>
    @endforeach
    <tr><td class="et">Consulta</td><td>{{ $c->tipo->getLabel() }}</td></tr>
</table>

@if ($d['clase'] === 'comparendos')
    @if ($d['paz_y_salvo'])
        <div class="resultado bien">NO REGISTRA COMPARENDOS NI MULTAS PENDIENTES DE PAGO</div>
        @if ($d['comparendos'])<p class="tenue">Registros anteriores que aparecen como pagados o resueltos:</p>@endif
    @else
        <div class="resultado mal">REGISTRA {{ $d['pendientes'] }} {{ $d['pendientes'] === 1 ? 'COMPARENDO O MULTA PENDIENTE' : 'COMPARENDOS O MULTAS PENDIENTES' }} · DEUDA {{ $pesos($d['deuda']) }}</div>
    @endif
    @if ($d['comparendos'])
        <table class="lista">
            <tr><th style="width: 15%">Número</th><th style="width: 9%">Fecha</th><th style="width: 9%">Tipo</th><th>Infracción</th><th style="width: 15%">Organismo</th><th style="width: 7%">Placa</th><th style="width: 11%">Estado</th><th class="der" style="width: 10%">Valor</th></tr>
            @foreach ($d['comparendos'] as $x)
                <tr>
                    <td>{{ $x['numero'] }}</td>
                    <td>{{ $fecha($x['fecha']) }}</td>
                    <td>{{ $x['tipo'] ? ucfirst((string) $x['tipo']) : '—' }}</td>
                    <td>{{ trim(($x['codigo'] ? $x['codigo'] . ' · ' : '') . ($x['infraccion'] ?? '')) ?: '—' }}</td>
                    <td>{{ trim(($x['organismo'] ?? '') . (($x['departamento'] ?? null) ? ' (' . $x['departamento'] . ')' : '')) ?: '—' }}</td>
                    <td>{{ $x['placa'] ?? '—' }}</td>
                    <td>{{ $x['estado_texto'] ?? \App\Models\ComparendoTransito::ESTADOS[$x['estado']] ?? $x['estado'] }}</td>
                    <td class="der">{{ $pesos($x['valor']) }}</td>
                </tr>
            @endforeach
            @unless ($d['paz_y_salvo'])
                <tr class="total"><td colspan="7">Total adeudado según la consulta</td><td class="der">{{ $pesos($d['deuda']) }}</td></tr>
            @endunless
        </table>
    @endif
@elseif ($d['clase'] === 'licencia')
    @if ($d['sin_licencia'])
        <div class="resultado neutro">EL RUNT NO REPORTA LICENCIA DE CONDUCCIÓN PARA ESTE DOCUMENTO</div>
    @else
        <div class="resultado {{ $d['suspendida'] ? 'mal' : 'bien' }}">{{ $d['suspendida'] ? 'LICENCIA DE CONDUCCIÓN SUSPENDIDA' : 'LICENCIA DE CONDUCCIÓN ' . mb_strtoupper($d['estado'] ?? 'REGISTRADA') }}</div>
        @if ($d['categorias'])
            <table class="lista">
                <tr><th>Categoría</th><th>Estado</th><th>Vence</th><th>Restricciones</th><th>Fin de la suspensión</th></tr>
                @foreach ($d['categorias'] as $l)
                    <tr><td>{{ $l['categoria'] ?? '—' }}</td><td>{{ $l['estado'] ?? '—' }}</td><td>{{ $fecha($l['vence'] ?? null) }}</td>
                        <td>{{ is_array($l['restricciones'] ?? null) ? implode(', ', $l['restricciones']) : ($l['restricciones'] ?? '—') }}</td><td>{{ $fecha($l['fin_suspension'] ?? null) }}</td></tr>
                @endforeach
            </table>
        @endif
        @if ($d['tiene_multas'] !== null)<p>El RUNT indica que {{ $d['tiene_multas'] ? 'SÍ' : 'NO' }} tiene multas asociadas.</p>@endif
    @endif
@else
    @if ($d['sin_registro'])
        <div class="resultado neutro">EL RUNT NO DEVOLVIÓ INFORMACIÓN PARA ESTA PLACA Y PROPIETARIO</div>
    @else
        <div class="resultado neutro">VEHÍCULO {{ mb_strtoupper($d['estado_vehiculo'] ?? 'REGISTRADO') }} EN EL RUNT</div>
        <table class="datos">
            <tr><td class="et">SOAT</td><td>{{ $d['soat_estado'] ?? '—' }} · vence {{ $fecha($d['soat_vence']) }}</td></tr>
            <tr><td class="et">Revisión técnico-mecánica</td><td>{{ $d['rtm_vigente'] === null ? '—' : ($d['rtm_vigente'] ? 'Vigente' : 'No vigente') }} · vence {{ $fecha($d['rtm_vence']) }}</td></tr>
        </table>
    @endif
@endif

<div class="nota">
    Constancia expedida por {{ $empresa->razon_social }} con el resultado de la consulta al {{ $fuente }} realizada el {{ $momento->format('d/m/Y') }} a las {{ $momento->format('h:i a') }}
    a través de un proveedor de información. Refleja lo que la fuente reportó en ese momento y puede cambiar después. No reemplaza el certificado oficial
    que expide el {{ $fuente === 'SIMIT' ? 'SIMIT (Federación Colombiana de Municipios)' : 'RUNT' }}. Verifique su autenticidad con el código QR.
</div>
</body>
</html>
