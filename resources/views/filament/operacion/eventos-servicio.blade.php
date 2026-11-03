@php
    $eventos = $getRecord()->eventos()->with(['conductor', 'usuario'])->get();
    $colores = ['leido' => '#1d4ed8', 'inicio' => '#c2410c', 'parada' => '#475569', 'novedad' => '#b91c1c', 'fin' => '#15803d', 'cancelado' => '#64748b'];
    $s = $getRecord();
@endphp
<div style="font-size: .875rem">
    @if ($s->km_inicial !== null || $s->km_final !== null)
        <p style="margin: 0 0 .6rem">Kilometraje: {{ $s->km_inicial !== null ? number_format($s->km_inicial, 0, ',', '.') : '—' }} → {{ $s->km_final !== null ? number_format($s->km_final, 0, ',', '.') : '—' }}
            @if ($s->km_inicial !== null && $s->km_final !== null) · <strong>{{ number_format($s->km_final - $s->km_inicial, 0, ',', '.') }} km</strong>@endif</p>
    @endif
    @forelse ($eventos as $e)
        <div style="display: flex; gap: .75rem; padding: .45rem 0; border-top: 1px solid rgba(148, 163, 184, .25)">
            <span style="flex: none; width: .6rem; height: .6rem; margin-top: .4rem; border-radius: 9999px; background: {{ $colores[$e->tipo] ?? '#64748b' }}"></span>
            <div>
                <strong>{{ $e->etiqueta() }}</strong>
                <span style="color: rgb(100 116 139)"> · {{ $e->created_at->timezone('America/Bogota')->format('d/m/Y h:i a') }} · {{ $e->conductor?->nombre_completo ?? $e->usuario?->name }}</span>
                @if ($e->kilometraje !== null)<span style="color: rgb(100 116 139)"> · {{ number_format($e->kilometraje, 0, ',', '.') }} km</span>@endif
                @if ($e->nota)<div>{{ $e->nota }}</div>@endif
                @if ($e->latitud !== null)
                    <a href="https://www.openstreetmap.org/?mlat={{ $e->latitud }}&mlon={{ $e->longitud }}#map=15/{{ $e->latitud }}/{{ $e->longitud }}" target="_blank" rel="noopener" style="color: rgb(194 65 12); font-weight: 600">Ver ubicación</a>
                @endif
            </div>
        </div>
    @empty
        <p style="margin: 0; color: rgb(100 116 139)">Sin reportes. En el portal, el conductor puede marcar «Leí el plan de viaje» y, si quiere, reportar inicio, paradas, novedades y fin.</p>
    @endforelse
</div>
