@extends('conductor.layout')
@section('titulo', 'Registrar firma')
@section('cuerpo')
<div class="tarjeta">
    <h1>Registre su firma</h1>
    <p class="tenue" style="margin-top:-6px">Se hace <strong>una sola vez</strong>. El sistema la pondrá en cada preoperacional que usted registre con su usuario.</p>
    @if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('conductor.firma.guardar') }}" id="form-firma">
        @csrf
        <div class="fila"><label style="margin:0">Dibuje su firma</label><button type="button" class="enlace" id="borrar">Borrar</button></div>
        <canvas class="lienzo" id="lienzo" aria-label="Recuadro para dibujar la firma" style="margin-top:6px"></canvas>
        <p class="nota">Use el dedo. Puede girar el celular para tener más espacio.</p>
        <input type="hidden" name="firma" id="firma">
        <div class="error" id="error-firma" style="display:none; margin-top:8px">Dibuje su firma en el recuadro.</div>

        <label class="check" style="margin-top:16px">
            <input type="checkbox" name="acepto" value="1" required>
            <span>Autorizo que esta firma se use en los preoperacionales que yo registre con mi usuario y clave. Entiendo que equivale a mi firma manuscrita (Ley 527 de 1999).</span>
        </label>
        <div class="botones"><button class="boton" type="submit">Guardar firma</button></div>
    </form>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var lienzo = document.getElementById('lienzo'), ctx = lienzo.getContext('2d');
    var trazos = 0, dibujando = false, ultimo = null;
    function ajustar() {
        var r = lienzo.getBoundingClientRect(), d = window.devicePixelRatio || 1;
        lienzo.width = Math.round(r.width * d); lienzo.height = Math.round(r.height * d);
        ctx.setTransform(d, 0, 0, d, 0, 0);
        ctx.lineWidth = 2.6; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
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
        var escala = Math.min(1, 900 / lienzo.width), salida = document.createElement('canvas');
        salida.width = Math.round(lienzo.width * escala); salida.height = Math.round(lienzo.height * escala);
        salida.getContext('2d').drawImage(lienzo, 0, 0, salida.width, salida.height);
        document.getElementById('firma').value = salida.toDataURL('image/png');
    });
    ajustar();
    window.addEventListener('resize', function () { if (trazos === 0) ajustar(); });
})();
</script>
@endpush
