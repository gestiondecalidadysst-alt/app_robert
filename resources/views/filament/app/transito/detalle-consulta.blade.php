{{-- Resultado de una consulta de tránsito (ventana «Ver resultado» del historial). --}}
@php
    $d = $c->detalle();
    $pesos = fn ($n) => '$ ' . number_format((float) $n, 0, ',', '.');
    $fecha = fn ($f) => $f ? \Illuminate\Support\Carbon::parse($f)->format('d/m/Y') : '—';
    $caja = fn (string $color) => "padding:.7rem .9rem; border-radius:.5rem; font-weight:700; margin-bottom:.75rem; border:2px solid {$color}; color:{$color}";
    $celda = 'padding:.35rem .45rem; border-top:1px solid rgba(148,163,184,.3); vertical-align:top';
@endphp
<div style="font-size:.875rem">
    @if ($d['clase'] === 'comparendos')
        @if ($d['paz_y_salvo'])
            <div style="{{ $caja('rgb(21 128 61)') }}">No registra comparendos ni multas pendientes de pago.</div>
        @else
            <div style="{{ $caja('rgb(185 28 28)') }}">Registra {{ $d['pendientes'] }} comparendo(s) o multa(s) pendiente(s). Deuda: {{ $pesos($d['deuda']) }}</div>
        @endif
        @if ($d['comparendos'])
            <div style="overflow-x:auto">
                <table style="width:100%; border-collapse:collapse; font-size:.8rem">
                    <thead><tr style="text-align:left; color:rgb(100 116 139)"><th style="padding:.35rem">Número</th><th>Fecha</th><th>Infracción</th><th>Organismo</th><th>Placa</th><th>Estado</th><th style="text-align:right">Valor</th></tr></thead>
                    <tbody>
                        @foreach ($d['comparendos'] as $x)
                            <tr>
                                <td style="{{ $celda }}">{{ $x['numero'] }}<div style="color:rgb(100 116 139)">{{ $x['tipo'] ? ucfirst((string) $x['tipo']) : '' }}</div></td>
                                <td style="{{ $celda }}">{{ $fecha($x['fecha']) }}</td>
                                <td style="{{ $celda }}">{{ trim(($x['codigo'] ? $x['codigo'] . ' · ' : '') . ($x['infraccion'] ?? '')) ?: '—' }}</td>
                                <td style="{{ $celda }}">{{ $x['organismo'] ?? '—' }}</td>
                                <td style="{{ $celda }}">{{ $x['placa'] ?? '—' }}</td>
                                <td style="{{ $celda }}">{{ $x['estado_texto'] ?? $x['estado'] }}</td>
                                <td style="{{ $celda }}; text-align:right; white-space:nowrap">{{ $pesos($x['valor']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @elseif ($d['clase'] === 'licencia')
        @if ($d['sin_licencia'])
            <div style="{{ $caja('rgb(71 85 105)') }}">El RUNT no reporta licencia de conducción para este documento.</div>
        @else
            <div style="{{ $caja($d['suspendida'] ? 'rgb(185 28 28)' : 'rgb(21 128 61)') }}">{{ $d['suspendida'] ? 'Licencia SUSPENDIDA' : 'Licencia ' . mb_strtolower($d['estado'] ?? 'registrada') }}</div>
            @foreach ($d['categorias'] as $l)
                <div>Categoría <strong>{{ $l['categoria'] ?? '—' }}</strong> · {{ $l['estado'] ?? '—' }} · vence {{ $fecha($l['vence'] ?? null) }}@if ($l['fin_suspension'] ?? null) · suspendida hasta {{ $fecha($l['fin_suspension']) }}@endif</div>
            @endforeach
        @endif
    @else
        @if ($d['sin_registro'])
            <div style="{{ $caja('rgb(71 85 105)') }}">El RUNT no devolvió información para esta placa y propietario.</div>
        @else
            <div style="{{ $caja('rgb(71 85 105)') }}">Vehículo {{ mb_strtolower($d['estado_vehiculo'] ?? 'registrado') }} en el RUNT</div>
            <div>SOAT: {{ $d['soat_estado'] ?? '—' }} · vence {{ $fecha($d['soat_vence']) }}</div>
            <div>Revisión técnico-mecánica: {{ $d['rtm_vigente'] === null ? '—' : ($d['rtm_vigente'] ? 'vigente' : 'no vigente') }} · vence {{ $fecha($d['rtm_vence']) }}</div>
        @endif
    @endif
</div>
