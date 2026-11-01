@php $total = $registros->sum(fn ($r) => (float) $r->valor); @endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Informe de mantenimientos</title>
<style>
    @page { margin: 26px 28px 30px 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 7.4px; color: #111; }
    table { width: 100%; border-collapse: collapse; }
    .logo { max-height: 42px; max-width: 110px; }
    .titulo { text-align: center; font-weight: bold; font-size: 10px; }
    .sub { text-align: center; font-size: 7.6px; color: #333; }
    .t td, .t th { border: 1px solid #9aa5b1; padding: 3px 4px; text-align: left; vertical-align: top; }
    .t th { background: #1B2B3A; color: #fff; font-size: 7px; }
    .num { text-align: right; white-space: nowrap; }
    .total td { background: #eef1f5; font-weight: bold; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 20%">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
        <td style="width: 60%">
            <div class="titulo">{{ mb_strtoupper($e['razon_social'] ?? '') }} · NIT {{ $e['nit'] ?? '' }}</div>
            <div class="titulo" style="margin-top: 3px">INFORME DE MANTENIMIENTOS</div>
            <div class="sub">{{ $vehiculo ? 'Vehículo '.$vehiculo->placa.($vehiculo->numero_interno ? ' (No. interno '.$vehiculo->numero_interno.')' : '') : 'Toda la flota' }} · del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</div>
        </td>
        <td style="width: 20%; text-align: right" class="sub">
            {{ $registros->count() }} mantenimientos<br>
            Preventivos: {{ $registros->where('tipo.value', 'preventivo')->count() }} · Correctivos: {{ $registros->where('tipo.value', 'correctivo')->count() }}
        </td>
    </tr>
</table>

<table class="t" style="margin-top: 8px">
    <tr>
        <th style="width: 6%">Fecha</th>
        @unless ($vehiculo)<th style="width: 6%">Placa</th>@endunless
        <th style="width: 7%">Tipo / acción</th>
        <th style="width: 6%">Km</th>
        <th>Actividad</th>
        <th style="width: 15%">Repuestos</th>
        <th style="width: 11%">Rutinas</th>
        <th style="width: 10%">Taller / responsable</th>
        <th style="width: 7%">Factura</th>
        <th style="width: 7%">Valor</th>
        <th style="width: 4%">Evid.</th>
    </tr>
    @forelse ($registros as $r)
        <tr>
            <td>{{ $r->fecha->format('d/m/Y') }}</td>
            @unless ($vehiculo)<td>{{ $r->vehiculo?->placa }}</td>@endunless
            <td>{{ $r->tipo->getLabel() }}<br>{{ $r->accion->getLabel() }}</td>
            <td class="num">{{ $r->kilometraje !== null ? number_format($r->kilometraje, 0, ',', '.') : '—' }}</td>
            <td>{{ $r->actividad }}@if ($r->problema)<br><span style="color: #555">Problema: {{ $r->problema }}</span>@endif</td>
            <td>{{ $r->repuestos ?: '—' }}</td>
            <td>{{ $r->rutinas->pluck('nombre')->implode('; ') ?: '—' }}</td>
            <td>{{ $r->taller ?: '—' }}@if ($r->responsable_nombre)<br>{{ $r->responsable_nombre }}@endif</td>
            <td>{{ $r->factura_numero ?: '—' }}</td>
            <td class="num">{{ $r->valor !== null ? '$ '.number_format((float) $r->valor, 0, ',', '.') : '—' }}</td>
            <td class="num">{{ $r->evidencias_count }}</td>
        </tr>
    @empty
        <tr><td colspan="11" style="text-align: center; padding: 10px">No hay mantenimientos en este rango.</td></tr>
    @endforelse
    @if ($registros->isNotEmpty())
        <tr class="total">
            <td colspan="{{ $vehiculo ? 8 : 9 }}" style="text-align: right">Total</td>
            <td class="num">$ {{ number_format($total, 0, ',', '.') }}</td>
            <td></td>
        </tr>
    @endif
</table>
<p class="sub" style="text-align: left; margin-top: 4px">Generado el {{ now()->format('d/m/Y h:i a') }}. Las evidencias (facturas y fotos) se consultan en el sistema.</p>
</body>
</html>
