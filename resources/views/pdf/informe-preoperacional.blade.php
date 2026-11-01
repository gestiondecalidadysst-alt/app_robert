{{-- Informe del preoperacional de un vehículo: ítems × días (máximo 31 días). --}}
@php
    $simbolo = ['si' => '✓', 'no' => '✗', 'na' => 'N/A'];
    $clave = fn (array $r) => $r['grupo'].'|'.$r['descripcion'];
    $respuestasDia = $porDia->map(fn ($i) => collect($i->respuestas)->keyBy($clave));
    $grupos = $filas->groupBy('grupo');
    $conFallas = $inspecciones->filter(fn ($i) => count($i->fallas()) > 0 || filled($i->observaciones));
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Informe preoperacional {{ $vehiculo->placa }}</title>
<style>
    @page { margin: 26px 26px 30px 26px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #111; }
    table { width: 100%; border-collapse: collapse; }
    .logo { max-height: 42px; max-width: 110px; }
    .titulo { text-align: center; font-weight: bold; font-size: 10px; }
    .sub { text-align: center; font-size: 7.6px; color: #333; }
    .m td, .m th { border: 1px solid #9aa5b1; padding: 2px 1px; text-align: center; }
    .m th { background: #1B2B3A; color: #fff; font-size: 6.6px; }
    .m td.item { text-align: left; padding-left: 4px; width: 25%; }
    .m tr.grupo td { background: #eef1f5; font-weight: bold; text-align: left; padding-left: 4px; }
    .no { color: #b91c1c; font-weight: bold; }
    .ok { color: #15803d; }
    .na { color: #6b7280; font-size: 5.6px; }
    .pie td { background: #f8fafc; font-size: 6.4px; }
    .lista td, .lista th { border: 1px solid #9aa5b1; padding: 3px 4px; text-align: left; vertical-align: top; }
    .lista th { background: #eef1f5; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 20%">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
        <td style="width: 60%">
            <div class="titulo">{{ mb_strtoupper($e['razon_social'] ?? '') }} · NIT {{ $e['nit'] ?? '' }}</div>
            <div class="titulo" style="margin-top: 3px">INFORME DE INSPECCIÓN PREOPERACIONAL</div>
            <div class="sub">Vehículo {{ $vehiculo->placa }}{{ $vehiculo->numero_interno ? ' (No. interno '.$vehiculo->numero_interno.')' : '' }} · del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</div>
        </td>
        <td style="width: 20%; text-align: right" class="sub">
            Días con preoperacional: {{ $porDia->count() }} de {{ $dias->count() }}<br>
            No aptos: {{ $porDia->filter(fn ($i) => ! $i->apta())->count() }}
        </td>
    </tr>
</table>

@if ($inspecciones->isEmpty())
    <p style="margin-top: 20px; text-align: center; font-size: 10px">No hay preoperacionales registrados en este rango.</p>
@else
<table class="m" style="margin-top: 8px">
    <tr>
        <th class="item" style="text-align: left">Ítem</th>
        @foreach ($dias as $dia)<th>{{ $dia->format('d') }}</th>@endforeach
    </tr>
    @foreach ($grupos as $grupo => $items)
        <tr class="grupo"><td colspan="{{ $dias->count() + 1 }}">{{ $grupo }}</td></tr>
        @foreach ($items as $item)
            <tr>
                <td class="item">{{ $item['descripcion'] }}{{ $item['critico'] ? ' *' : '' }}</td>
                @foreach ($dias as $dia)
                    @php $r = $respuestasDia->get($dia->toDateString())?->get($clave($item)); @endphp
                    <td class="{{ match ($r['respuesta'] ?? null) { 'no' => 'no', 'si' => 'ok', 'na' => 'na', default => '' } }}">{{ $r ? $simbolo[$r['respuesta']] : '' }}</td>
                @endforeach
            </tr>
        @endforeach
    @endforeach
    <tr class="pie">
        <td class="item"><strong>Resultado</strong></td>
        @foreach ($dias as $dia)
            @php $i = $porDia->get($dia->toDateString()); @endphp
            <td class="{{ $i ? ($i->apta() ? 'ok' : 'no') : '' }}">{{ $i ? ($i->apta() ? 'A' : 'NA') : '—' }}</td>
        @endforeach
    </tr>
    <tr class="pie">
        <td class="item"><strong>Kilometraje</strong></td>
        @foreach ($dias as $dia)
            @php $i = $porDia->get($dia->toDateString()); @endphp
            <td style="font-size: 5.2px">{{ $i?->kilometraje !== null ? number_format($i->kilometraje, 0, '', '.') : '' }}</td>
        @endforeach
    </tr>
    <tr class="pie">
        <td class="item"><strong>Conductor (C.C.)</strong></td>
        @foreach ($dias as $dia)
            @php $i = $porDia->get($dia->toDateString()); @endphp
            <td style="font-size: 5px">{{ $i ? substr((string) ($i->datos['conductor']['documento'] ?? ''), -4) : '' }}</td>
        @endforeach
    </tr>
</table>
<p class="sub" style="text-align: left; margin: 4px 0 0">✓ cumple · ✗ no cumple · N/A no aplica · * ítem crítico · A apto · NA no apto · conductor: últimos 4 dígitos del documento. Cada preoperacional tiene su PDF con la firma del conductor y su código de verificación.</p>

@if ($conFallas->isNotEmpty())
    <p style="font-weight: bold; margin: 10px 0 4px; font-size: 8px">Novedades y observaciones</p>
    <table class="lista">
        <tr><th style="width: 11%">Fecha</th><th style="width: 18%">Conductor</th><th style="width: 34%">No cumple</th><th>Observaciones</th></tr>
        @foreach ($conFallas as $i)
            <tr>
                <td>{{ $i->fecha->format('d/m/Y') }} {{ $i->created_at->format('h:i a') }}</td>
                <td>{{ $i->datos['conductor']['nombre'] ?? '' }}</td>
                <td>{{ collect($i->fallas())->map(fn ($f) => $f['descripcion'].($f['observacion'] ? ': '.$f['observacion'] : ''))->implode(' · ') ?: '—' }}</td>
                <td>{{ $i->observaciones ?: '—' }}</td>
            </tr>
        @endforeach
    </table>
@endif
@endif
</body>
</html>
