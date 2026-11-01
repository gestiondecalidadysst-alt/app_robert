@extends('conductor.layout')
@section('titulo', 'Preoperacional '.$vehiculo->placa)
@push('cabeza')
<style>
    .item { padding: 12px 0; border-top: 1px solid #eef2f6; }
    .item:first-of-type { border-top: 0; }
    .item .desc { font-size: 15px; margin-bottom: 8px; }
    .crit { display: inline-block; font-size: 10px; font-weight: 800; color: #b91c1c; border: 1px solid #fca5a5; border-radius: 4px; padding: 0 4px; margin-left: 4px; vertical-align: 2px; }
    .opciones { display: grid; grid-template-columns: 1fr 1fr .7fr; gap: 6px; }
    .opciones input { position: absolute; opacity: 0; pointer-events: none; }
    .opciones label { margin: 0; text-align: center; padding: 11px 4px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-size: 14px; font-weight: 600; color: #334155; cursor: pointer; user-select: none; }
    .opciones input:checked + label.si { background: #15803d; border-color: #15803d; color: #fff; }
    .opciones input:checked + label.no { background: #b91c1c; border-color: #b91c1c; color: #fff; }
    .opciones input:checked + label.na { background: #475569; border-color: #475569; color: #fff; }
    .opciones input:focus-visible + label { outline: 2px solid #fb923c; }
    .obs { margin-top: 8px; display: none; }
    .item.falla .obs { display: block; }
    .item.sin-responder .desc { color: #b91c1c; }
    .grupo-titulo { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: var(--tenue); margin: 0 0 4px; }
    .progreso { position: sticky; top: 72px; z-index: 4; background: var(--fondo); padding: 6px 0 10px; }
    .barra-prog { height: 8px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
    .barra-prog div { height: 100%; width: 0; background: var(--naranja); transition: width .2s; }
</style>
@endpush
@section('cuerpo')
<p style="margin:0 0 6px"><a class="enlace" href="{{ route('conductor.inicio') }}">← Volver</a></p>
<h1 style="margin-bottom:4px">Preoperacional · {{ $vehiculo->placa }}</h1>
<p class="tenue" style="margin:0 0 12px">{{ now()->format('d/m/Y') }} · {{ $conductor->nombre_completo }}{{ $fuec ? ' · FUEC '.$fuec->numero_formateado : '' }}</p>

@if ($anterior && ! $anterior->apta())
    <div class="alerta">El preoperacional de hoy quedó NO APTO a las {{ $anterior->created_at->format('h:i a') }}. Hágalo de nuevo solo si ya se corrigió la falla.</div>
@endif
@if ($errors->any())
    <div class="error">
        @foreach (collect($errors->all())->unique()->take(5) as $mensaje)<div>{{ $mensaje }}</div>@endforeach
    </div>
@endif

<form method="post" action="{{ route('conductor.preoperacional.guardar', ['vehiculo' => $vehiculo, 'fuec' => $fuec?->id]) }}" id="form-preop" novalidate>
    @csrf
    <input type="hidden" name="latitud" id="latitud" value="{{ old('latitud') }}">
    <input type="hidden" name="longitud" id="longitud" value="{{ old('longitud') }}">

    <div class="tarjeta">
        <label for="kilometraje">Kilometraje actual</label>
        <input type="number" id="kilometraje" name="kilometraje" inputmode="numeric" min="0" value="{{ old('kilometraje') }}" required placeholder="{{ $ultimoKm ? 'Último: '.number_format($ultimoKm, 0, ',', '.') : 'Ej: 125430' }}">
    </div>

    <div class="progreso"><div class="fila tenue" style="font-size:13px; margin-bottom:4px"><span>Revisión</span><span><span id="hechos">0</span> de {{ $grupos->flatten()->count() }}</span></div><div class="barra-prog"><div id="prog"></div></div></div>

    @foreach ($grupos as $grupo => $items)
        <div class="tarjeta">
            <p class="grupo-titulo">{{ $grupo }}</p>
            @foreach ($items as $item)
                @php $valor = old("respuestas.{$item->id}.respuesta"); @endphp
                <div class="item {{ $valor === 'no' ? 'falla' : '' }}" data-item>
                    <div class="desc">{{ $item->descripcion }}@if ($item->critico)<span class="crit" title="Si no cumple, el vehículo no puede operar">CRÍTICO</span>@endif</div>
                    <div class="opciones" role="radiogroup" aria-label="{{ $item->descripcion }}">
                        @foreach (['si' => 'Cumple', 'no' => 'No cumple', 'na' => 'N/A'] as $clave => $texto)
                            <input type="radio" id="r{{ $item->id }}{{ $clave }}" name="respuestas[{{ $item->id }}][respuesta]" value="{{ $clave }}" @checked($valor === $clave)>
                            <label class="{{ $clave }}" for="r{{ $item->id }}{{ $clave }}">{{ $texto }}</label>
                        @endforeach
                    </div>
                    <div class="obs">
                        <textarea name="respuestas[{{ $item->id }}][observacion]" rows="2" placeholder="¿Qué falla? (obligatorio)">{{ old("respuestas.{$item->id}.observacion") }}</textarea>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="tarjeta">
        <label for="observaciones">Observaciones generales (opcional)</label>
        <textarea id="observaciones" name="observaciones" rows="3">{{ old('observaciones') }}</textarea>

        <label class="check" style="margin-top:14px">
            <input type="checkbox" name="declaro" value="1" required>
            <span>Declaro que revisé personalmente el vehículo y que la información es verdadera. Al enviar, se pondrá mi firma registrada.</span>
        </label>
        <div class="error" id="error-js" style="display:none; margin-top:10px"></div>
        <div class="botones"><button class="boton" type="submit">Enviar preoperacional</button></div>
    </div>
</form>
@endsection
@push('scripts')
<script>
(function () {
    var form = document.getElementById('form-preop');
    var items = Array.prototype.slice.call(form.querySelectorAll('[data-item]'));
    function actualizar() {
        var hechos = 0;
        items.forEach(function (it) {
            var marcado = it.querySelector('input[type=radio]:checked');
            if (marcado) { hechos++; it.classList.remove('sin-responder'); }
            it.classList.toggle('falla', !!marcado && marcado.value === 'no');
        });
        document.getElementById('hechos').textContent = hechos;
        document.getElementById('prog').style.width = (items.length ? hechos * 100 / items.length : 0) + '%';
    }
    form.addEventListener('change', actualizar);
    actualizar();

    form.addEventListener('submit', function (e) {
        var errores = [], primero = null;
        if (!form.kilometraje.value) { errores.push('Escriba el kilometraje actual.'); primero = primero || form.kilometraje; }
        items.forEach(function (it) {
            var marcado = it.querySelector('input[type=radio]:checked');
            if (!marcado) { it.classList.add('sin-responder'); primero = primero || it; }
            else if (marcado.value === 'no' && !it.querySelector('textarea').value.trim()) { primero = primero || it; errores.push('Explique cada falla.'); }
        });
        var faltan = items.filter(function (it) { return !it.querySelector('input[type=radio]:checked'); }).length;
        if (faltan) errores.unshift(faltan === 1 ? 'Falta responder 1 ítem.' : 'Faltan responder ' + faltan + ' ítems.');
        if (!form.declaro.checked) errores.push('Confirme que hizo la revisión.');
        if (errores.length) {
            e.preventDefault();
            var caja = document.getElementById('error-js');
            caja.innerHTML = errores.filter(function (v, i, a) { return a.indexOf(v) === i; }).join('<br>');
            caja.style.display = 'block';
            if (primero) primero.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    // Ubicación opcional: si el conductor la permite, queda como evidencia.
    if (navigator.geolocation && !document.getElementById('latitud').value) {
        navigator.geolocation.getCurrentPosition(function (p) {
            document.getElementById('latitud').value = p.coords.latitude.toFixed(7);
            document.getElementById('longitud').value = p.coords.longitude.toFixed(7);
        }, function () {}, { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 });
    }
})();
</script>
@endpush
