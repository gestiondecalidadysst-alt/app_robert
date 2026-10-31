@extends('firma.layout')
@php
    $estado = $documento->estadoFirma();
    $numero = $documento->datos['valores']['contrato.numero'] ?? '';
    $clase = match ($estado->value) { 'firmado' => 'ok', 'anulado', 'vencido' => 'mal', 'emitido' => 'gris', default => 'info' };
    $canalActivo = $codigoActivo?->canal;
@endphp
@section('titulo', "Contrato No. {$numero}")
@section('cuerpo')
<div class="barra">
    <small>{{ $empresa['razon_social'] ?? '' }}</small>
    <strong>Contrato de prestación de servicios No. {{ $numero }}</strong>
</div>

<div class="contenedor">
    @if (session('firmado'))
        <div class="aviso"><strong>¡Listo! El contrato quedó firmado.</strong> Puede descargar su copia firmada{{ $documento->firmante_email ? ' y también se la enviamos por correo' : '' }}.</div>
    @endif

    <div class="tarjeta">
        <div class="fila" style="flex-wrap: wrap">
            <span class="estado {{ $clase }}">{{ $estado->getLabel() }}</span>
            <a class="boton sec" style="width:auto; padding:10px 14px; font-size:14px" href="{{ route('contrato.firma.pdf', $token) }}">
                {{ $documento->firmado() ? 'Descargar contrato firmado (PDF)' : 'Ver en PDF' }}
            </a>
        </div>
        @if ($documento->firmado())
            <p class="tenue" style="margin-bottom:0">Firmado por {{ $documento->firmante_nombre }} el {{ $documento->firmado_at->format('d/m/Y \a \l\a\s h:i a') }}.</p>
        @elseif ($estado->value === 'vencido')
            <p class="tenue" style="margin-bottom:0">Este enlace venció el {{ $documento->token_expira_at->format('d/m/Y') }}. Pida a la empresa que se lo envíe de nuevo.</p>
        @else
            <p class="tenue" style="margin-bottom:0">Lea el contrato completo. Al final encontrará el paso para firmarlo. El enlace vence el {{ $documento->token_expira_at?->format('d/m/Y') }}.</p>
        @endif
    </div>

    <div class="tarjeta documento">
        {!! $documento->contenido !!}
    </div>

    @if ($documento->admiteFirma())
        <div class="tarjeta" id="firmar">
            <h2 style="margin-top:0; font-size:18px">Firmar el contrato</h2>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif
            @if (session('codigo_enviado'))
                <div class="aviso">Le enviamos el código {{ session('codigo_enviado') }}. Vence en 10 minutos.</div>
            @endif

            @if (! $codigoActivo)
                @if ($canales === [])
                    <p>No hay un celular ni un correo registrados para enviarle el código. Comuníquese con la empresa.</p>
                @else
                    <p style="margin-top:0">Para confirmar que es usted, le enviaremos un <strong>código de 6 dígitos</strong>
                        {{ $canales[0] === 'sms' ? 'por mensaje de texto al celular '.$destinos['sms'] : 'al correo '.$destinos['correo'] }}.</p>
                    <form method="post" action="{{ route('contrato.firma.codigo', $token) }}">
                        @csrf
                        <input type="hidden" name="canal" value="{{ $canales[0] }}">
                        <button class="boton" type="submit">Enviar código</button>
                    </form>
                    @if (count($canales) > 1)
                        <form method="post" action="{{ route('contrato.firma.codigo', $token) }}" style="margin-top:12px">
                            @csrf
                            <input type="hidden" name="canal" value="{{ $canales[1] }}">
                            <button class="enlace" type="submit">Prefiero recibirlo {{ $canales[1] === 'sms' ? 'por SMS' : 'por correo' }}</button>
                        </form>
                    @endif
                @endif
            @else
                <form method="post" action="{{ route('contrato.firma.firmar', $token) }}" id="form-firma">
                    @csrf
                    <label for="codigo">Código de verificación ({{ $canalActivo === 'sms' ? 'SMS a '.$destinos['sms'] : 'correo '.$destinos['correo'] }})</label>
                    <input type="text" id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required placeholder="••••••">

                    <div style="margin-top:18px">
                        <div class="fila"><label style="margin:0">Dibuje su firma</label><button type="button" class="enlace" id="borrar">Borrar</button></div>
                        <canvas class="lienzo" id="lienzo" aria-label="Recuadro para dibujar la firma"></canvas>
                        <p class="tenue" style="margin:6px 0 0">Use el dedo o el mouse.</p>
                        <input type="hidden" name="firma" id="firma">
                        <div class="error" id="error-firma" style="display:none; margin-top:8px">Dibuje su firma en el recuadro.</div>
                    </div>

                    <label class="check" style="margin-top:18px">
                        <input type="checkbox" name="acepto" value="1" required @checked(old('acepto'))>
                        <span>He leído el contrato y acepto firmarlo electrónicamente. Entiendo que esta firma tiene la misma validez que la firma manuscrita (Ley 527 de 1999).</span>
                    </label>

                    <button class="boton" type="submit" style="margin-top:18px">Firmar contrato</button>
                </form>

                <div style="margin-top:16px" class="tenue">
                    ¿No le llegó el código?
                    <span id="espera" @if ($segundosReenvio <= 0) style="display:none" @endif>Podrá pedir otro en <span id="segundos">{{ $segundosReenvio }}</span> s.</span>
                    <span id="reenvio" @if ($segundosReenvio > 0) style="display:none" @endif>
                        @foreach ($canales as $canal)
                            <form method="post" action="{{ route('contrato.firma.codigo', $token) }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="canal" value="{{ $canal }}">
                                <button class="enlace" type="submit">Enviar {{ $canal === $canalActivo ? 'otro ' : '' }}{{ $canal === 'sms' ? 'por SMS' : 'por correo' }}</button>
                            </form>@if (! $loop->last) · @endif
                        @endforeach
                    </span>
                </div>
            @endif
        </div>
    @endif

    <div class="pie">
        Código de verificación del documento: {{ $documento->codigo_verificacion }}<br>
        Si usted no esperaba este mensaje, ignórelo.
    </div>
</div>

@if ($codigoActivo && $documento->admiteFirma())
<script>
(function () {
    var lienzo = document.getElementById('lienzo');
    var ctx = lienzo.getContext('2d');
    var trazos = 0, dibujando = false, ultimo = null;

    function ajustar() {
        var r = lienzo.getBoundingClientRect(), d = window.devicePixelRatio || 1;
        lienzo.width = Math.round(r.width * d); lienzo.height = Math.round(r.height * d);
        ctx.setTransform(d, 0, 0, d, 0, 0);
        ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
        trazos = 0;
    }
    function punto(e) { var r = lienzo.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; }

    lienzo.addEventListener('pointerdown', function (e) { dibujando = true; ultimo = punto(e); lienzo.setPointerCapture(e.pointerId); e.preventDefault(); });
    lienzo.addEventListener('pointermove', function (e) {
        if (!dibujando) return;
        var p = punto(e);
        ctx.beginPath(); ctx.moveTo(ultimo.x, ultimo.y); ctx.lineTo(p.x, p.y); ctx.stroke();
        ultimo = p; trazos++; e.preventDefault();
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (t) { lienzo.addEventListener(t, function () { dibujando = false; }); });
    document.getElementById('borrar').addEventListener('click', function () { ctx.clearRect(0, 0, lienzo.width, lienzo.height); trazos = 0; });

    document.getElementById('form-firma').addEventListener('submit', function (e) {
        if (trazos < 8) { e.preventDefault(); document.getElementById('error-firma').style.display = 'block'; return; }
        // Exporta a un tamaño moderado (máx. 900 px de ancho) para que el archivo sea liviano.
        var escala = Math.min(1, 900 / lienzo.width), salida = document.createElement('canvas');
        salida.width = Math.round(lienzo.width * escala); salida.height = Math.round(lienzo.height * escala);
        salida.getContext('2d').drawImage(lienzo, 0, 0, salida.width, salida.height);
        document.getElementById('firma').value = salida.toDataURL('image/png');
    });

    ajustar();
    window.addEventListener('resize', function () { if (trazos === 0) ajustar(); });

    var s = {{ (int) $segundosReenvio }};
    if (s > 0) {
        var t = setInterval(function () {
            s--; document.getElementById('segundos').textContent = s;
            if (s <= 0) { clearInterval(t); document.getElementById('espera').style.display = 'none'; document.getElementById('reenvio').style.display = 'inline'; }
        }, 1000);
    }
})();
</script>
@endif
@endsection
