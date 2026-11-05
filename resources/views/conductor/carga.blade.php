@extends('conductor.layout')
@section('titulo', 'Tiempos de carga')

@section('cuerpo')
    <h1>Tiempos de carga</h1>

    @if (session('mensaje'))
        <div class="tarjeta" style="border-left: 5px solid var(--verde)">{{ session('mensaje') }}</div>
    @endif

    @if ($errors->any())
        <div class="tarjeta" style="border-left: 5px solid var(--rojo)">
            @foreach ($errors->all() as $error)
                <div class="dato">{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @forelse ($manifiestos as $m)
        <div class="tarjeta hoy">
            <div class="fila">
                <div>
                    <div class="grande">{{ $m->numeroFormateado() }}</div>
                    <div class="numero">{{ $m->vehiculo?->placa }} · {{ $m->fecha_expedicion?->format('d/m/Y') }}</div>
                </div>
                <span class="estado info">{{ $m->remesas->count() }} remesa{{ $m->remesas->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="dato"><b>{{ $m->origen?->nombre }}</b> → <b>{{ $m->destino?->nombre }}</b></div>

            @foreach ($m->remesas as $r)
                <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--borde)">
                    <div class="dato"><b>{{ $r->numeroFormateado() }}</b> · {{ $r->producto_descripcion }}</div>
                    <div class="dato" style="color: var(--tenue)">
                        Cargue: {{ $r->remitente?->municipio?->nombre ?? '—' }} ·
                        Descargue: {{ $r->destinatario?->municipio?->nombre ?? '—' }}
                    </div>

                    {{-- Lo ya reportado, para que el conductor vea en qué va --}}
                    @foreach (['cargue', 'descargue'] as $punto)
                        @php $hechos = collect(['llegada', 'entrada', 'salida'])->filter(fn ($e) => isset($r->marcados["{$punto}.{$e}"])); @endphp
                        @if ($hechos->isNotEmpty())
                            <div class="dato">
                                <b>{{ ucfirst($punto) }}:</b>
                                @foreach ($hechos as $e)
                                    {{ ucfirst($e) }} {{ $r->marcados["{$punto}.{$e}"] }}@if (! $loop->last) · @endif
                                @endforeach
                            </div>
                        @endif
                    @endforeach

                    @if ($r->siguiente_evento)
                        <form method="POST" action="{{ route('conductor.carga.marcar', $r) }}" class="botones" data-ubicacion>
                            @csrf
                            <input type="hidden" name="punto" value="{{ $r->siguiente_punto }}">
                            <input type="hidden" name="evento" value="{{ $r->siguiente_evento }}">
                            <input type="hidden" name="latitud" value="">
                            <input type="hidden" name="longitud" value="">
                            <button type="submit" class="boton">
                                Reportar {{ $r->siguiente_evento }} en {{ $r->siguiente_punto }}
                            </button>
                        </form>
                    @else
                        <div class="dato" style="color: var(--verde); font-weight: 700; margin-top: 8px">Tiempos completos ✓</div>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <div class="tarjeta">
            <div class="dato">No tiene manifiestos de carga en curso.</div>
        </div>
    @endforelse

    <a href="{{ route('conductor.inicio') }}" class="boton sec" style="margin-top: 8px">Volver</a>

    <script>
        // La hora la pone el servidor; aquí solo se intenta añadir la ubicación.
        // Si el navegador la niega o tarda, el reporte se envía igual: un tiempo sin
        // coordenadas sirve, uno que no se envía no.
        document.querySelectorAll('form[data-ubicacion]').forEach(function (form) {
            form.addEventListener('submit', function (evento) {
                if (form.dataset.listo || !navigator.geolocation) { return; }

                evento.preventDefault();
                var boton = form.querySelector('button');
                boton.disabled = true;
                boton.textContent = 'Reportando…';

                var enviar = function () {
                    form.dataset.listo = '1';
                    form.submit();
                };

                navigator.geolocation.getCurrentPosition(function (pos) {
                    form.querySelector('[name=latitud]').value = pos.coords.latitude.toFixed(7);
                    form.querySelector('[name=longitud]').value = pos.coords.longitude.toFixed(7);
                    enviar();
                }, enviar, { timeout: 6000, maximumAge: 60000 });
            });
        });
    </script>
@endsection
