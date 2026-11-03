@extends('conductor.layout')
@section('titulo', 'Plan de viaje')
@push('cabeza')
<style>
    .punto { display: flex; gap: 10px; padding: 9px 0; border-top: 1px solid var(--borde); }
    .punto:first-child { border-top: 0; }
    .marca-p { flex: none; width: 28px; height: 28px; border-radius: 999px; display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 13px; }
    .critico { background: #b91c1c; } .parada { background: #15803d; } .control { background: #1d4ed8; } .emergencia { background: #7c3aed; }
    details.reporte { border: 1px solid var(--borde); border-radius: 12px; padding: 12px 14px; margin-top: 10px; background: #fff; }
    details.reporte summary { font-weight: 700; cursor: pointer; }
    .requisitos li { margin-bottom: 4px; }
    .linea-ev { font-size: 13px; color: #334155; padding: 6px 0; border-top: 1px solid var(--borde); }
</style>
@endpush
@section('cuerpo')
@php
    use App\Enums\TipoPuntoRuta;
    $r = $s->ruta;
    $empresa = \App\Models\Empresa::find($s->empresa_id);
    $enCurso = $s->estado->value === 'en_curso';
    $finalizado = $s->estado->value === 'finalizado';
@endphp

<p style="margin:0 0 10px"><a class="enlace" href="{{ route('conductor.inicio') }}">← Mis servicios</a></p>
@if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

<article class="tarjeta hoy">
    <div class="fila">
        <span class="tenue" style="font-size:13px">Plan de viaje{{ $s->fuec ? ' · FUEC ' . $s->fuec->numero_formateado : '' }}</span>
        <span class="estado {{ $finalizado ? 'ok' : ($enCurso ? 'pend' : 'info') }}">{{ $s->estado->getLabel() }}</span>
    </div>
    <div class="grande">{{ $s->origen?->nombre }} → {{ $s->destino?->nombre }}</div>
    <p class="dato"><b>{{ $s->horario()->texto() }}</b> · Vehículo <b>{{ $s->placa ?? 'por asignar' }}</b></p>
    @if ($s->recorrido)<p class="dato tenue" style="font-size:13px">{{ $s->recorrido }}</p>@endif
    @if ($r)
        <p class="dato">{{ $r->distancia_km ? $r->distancia_km . ' km · ' : '' }}{{ $r->duracion_min ? \App\Models\Ruta::duracionTexto($r->duracion_min) . ' de conducción' : '' }}{{ $r->velocidad_maxima ? ' · máximo ' . $r->velocidad_maxima . ' km/h' : '' }}</p>
    @endif

    @if (! $s->leido_at)
        <form method="post" action="{{ route('conductor.servicio.reportar', $s) }}" class="botones con-ubicacion">
            @csrf
            <input type="hidden" name="tipo" value="leido">
            <button class="boton" type="submit">✓ Leí el plan de viaje</button>
        </form>
        <p class="nota">Lea los puntos críticos, las paradas y las velocidades antes de salir.</p>
    @else
        <div class="linea-estado" style="margin-top:10px"><span class="estado ok">✓ Leído</span> el {{ $s->leido_at->timezone('America/Bogota')->format('d/m/Y h:i a') }}</div>
    @endif
    <div class="botones"><a class="boton sec" href="{{ route('conductor.servicio.pdf', $s) }}" target="_blank" rel="noopener">Descargar plan de viaje (PDF)</a></div>
</article>

<article class="tarjeta">
    <h2 style="margin:0 0 6px; font-size:16px">Rutograma{{ $r ? ': ' . $r->nombre : '' }}</h2>
    @if ($r && $r->puntosDe()->isNotEmpty())
        @foreach ($r->puntosDe() as $p)
            @php $tipo = TipoPuntoRuta::tryFrom($p['tipo'] ?? ''); @endphp
            <div class="punto">
                <span class="marca-p {{ $tipo?->value }}">{{ $tipo?->icono() }}</span>
                <div>
                    <b>{{ $p['nombre'] }}</b>
                    <div class="tenue" style="font-size:13px">{{ $tipo?->getLabel() }}{{ ! empty($p['referencia']) ? ' · ' . $p['referencia'] : '' }}{{ ! empty($p['velocidad']) ? ' · ' . $p['velocidad'] . ' km/h' : '' }}</div>
                    @if (! empty($p['indicacion']))<div style="font-size:14px">{{ $p['indicacion'] }}</div>@endif
                </div>
            </div>
        @endforeach
    @else
        <p class="tenue" style="margin:0">La empresa no registró puntos para esta ruta. Identifique los puntos críticos y las paradas seguras antes de salir.</p>
    @endif
    @if ($r?->rutas_alternas)<p class="dato" style="margin-top:10px"><b>Rutas alternas:</b> {{ $r->rutas_alternas }}</p>@endif
    @if ($r?->contactos_emergencia)<p class="dato"><b>Emergencias:</b> {{ $r->contactos_emergencia }}</p>@endif
    @if ($r?->recomendaciones)<p class="dato"><b>Recomendaciones:</b> {{ $r->recomendaciones }}</p>@endif
</article>

<article class="tarjeta">
    <h2 style="margin:0 0 6px; font-size:16px">Antes y durante el viaje</h2>
    <ul class="requisitos" style="margin:0; padding-left:18px; font-size:14px">
        <li>Preoperacional de hoy hecho y APTO; documentos y extracto a bordo.</li>
        <li>Máximo {{ $empresa->jornada_max_horas_continuas }} h de conducción continua y {{ $empresa->jornada_max_horas_dia }} h al día. Use las paradas seguras.</li>
        <li>Respete las velocidades. No use el celular mientras conduce.</li>
        <li>Cinturón para todos los ocupantes.</li>
    </ul>
</article>

@unless ($finalizado)
<article class="tarjeta">
    <h2 style="margin:0 0 4px; font-size:16px">Reportar el viaje <span class="tenue" style="font-weight:400">(opcional)</span></h2>
    <p class="nota" style="margin:0 0 6px">Si su celular lo permite, se guarda la ubicación del reporte. Hágalo con el vehículo detenido.</p>

    @unless ($enCurso)
        <details class="reporte" open>
            <summary>Iniciar viaje</summary>
            <form method="post" action="{{ route('conductor.servicio.reportar', $s) }}" class="con-ubicacion" style="margin-top:10px">
                @csrf
                <input type="hidden" name="tipo" value="inicio">
                <div class="campo"><label for="km-i">Kilometraje inicial</label><input id="km-i" type="number" name="kilometraje" inputmode="numeric" min="0"></div>
                <button class="boton" type="submit">Iniciar viaje</button>
            </form>
        </details>
    @endunless

    <details class="reporte">
        <summary>Registrar parada</summary>
        <form method="post" action="{{ route('conductor.servicio.reportar', $s) }}" class="con-ubicacion" style="margin-top:10px">
            @csrf
            <input type="hidden" name="tipo" value="parada">
            <div class="campo"><label for="nota-p">Dónde / motivo (opcional)</label><input id="nota-p" type="text" name="nota" maxlength="300"></div>
            <button class="boton sec" type="submit">Registrar parada</button>
        </form>
    </details>

    <details class="reporte">
        <summary>Reportar novedad</summary>
        <form method="post" action="{{ route('conductor.servicio.reportar', $s) }}" class="con-ubicacion" style="margin-top:10px">
            @csrf
            <input type="hidden" name="tipo" value="novedad">
            <div class="campo"><label for="nota-n">¿Qué pasó?</label><textarea id="nota-n" name="nota" rows="3" maxlength="1000" required>{{ old('nota') }}</textarea></div>
            <button class="boton sec" type="submit">Enviar novedad a la empresa</button>
        </form>
    </details>

    @if ($enCurso)
        <details class="reporte" open>
            <summary>Finalizar viaje</summary>
            <form method="post" action="{{ route('conductor.servicio.reportar', $s) }}" class="con-ubicacion" style="margin-top:10px">
                @csrf
                <input type="hidden" name="tipo" value="fin">
                <div class="campo"><label for="km-f">Kilometraje final</label><input id="km-f" type="number" name="kilometraje" inputmode="numeric" min="{{ $s->km_inicial ?? 0 }}"></div>
                <div class="campo"><label for="nota-f">Novedades (opcional)</label><input id="nota-f" type="text" name="nota" maxlength="500"></div>
                <button class="boton" type="submit">Finalizar viaje</button>
            </form>
        </details>
    @endif
</article>
@endunless

@if ($s->eventos->isNotEmpty())
<article class="tarjeta">
    <h2 style="margin:0 0 4px; font-size:16px">Registro</h2>
    @foreach ($s->eventos as $e)
        <div class="linea-ev"><b>{{ $e->etiqueta() }}</b> · {{ $e->created_at->timezone('America/Bogota')->format('d/m h:i a') }}{{ $e->kilometraje !== null ? ' · ' . number_format($e->kilometraje, 0, ',', '.') . ' km' : '' }}{{ $e->nota ? ' · ' . $e->nota : '' }}</div>
    @endforeach
</article>
@endif
@endsection

@push('scripts')
<script>
    // Ubicación opcional: si el conductor la autoriza, se adjunta al reporte; si no, se envía sin ella.
    document.querySelectorAll('form.con-ubicacion').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (form.dataset.listo || !navigator.geolocation) return;
            ev.preventDefault();
            var enviar = function () { form.dataset.listo = '1'; form.submit(); };
            var t = setTimeout(enviar, 6000);
            navigator.geolocation.getCurrentPosition(function (p) {
                clearTimeout(t);
                [['latitud', p.coords.latitude], ['longitud', p.coords.longitude]].forEach(function (c) {
                    var i = document.createElement('input'); i.type = 'hidden'; i.name = c[0]; i.value = c[1]; form.appendChild(i);
                });
                enviar();
            }, function () { clearTimeout(t); enviar(); }, { timeout: 5000, maximumAge: 60000 });
        });
    });
</script>
@endpush
