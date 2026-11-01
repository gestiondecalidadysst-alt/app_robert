@extends('conductor.layout')
@section('titulo', 'Mantenimiento '.$vehiculo->placa)
@push('cabeza')
<style>
    select { width: 100%; font-size: 16px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; }
    .opciones2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .opciones2 input { position: absolute; opacity: 0; pointer-events: none; }
    .opciones2 label { margin: 0; text-align: center; padding: 12px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-weight: 600; cursor: pointer; }
    .opciones2 input:checked + label { background: var(--azul); border-color: var(--azul); color: #fff; }
    .archivo { display: block; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 18px; text-align: center; background: #fff; cursor: pointer; }
    .archivo input { display: none; }
    #lista-archivos { font-size: 13px; color: var(--tenue); margin-top: 8px; }
</style>
@endpush
@section('cuerpo')
<p style="margin:0 0 6px"><a class="enlace" href="{{ route('conductor.mantenimiento') }}">← Volver</a></p>
<h1 style="margin-bottom:4px">Mantenimiento · {{ $vehiculo->placa }}</h1>
<p class="tenue" style="margin:0 0 12px">{{ $kmActual ? 'Último kilometraje reportado: '.number_format($kmActual, 0, ',', '.').' km' : 'Registre lo que se le hizo al vehículo, con la evidencia.' }}</p>

@if ($errors->any())
    <div class="error">@foreach (collect($errors->all())->unique()->take(5) as $m)<div>{{ $m }}</div>@endforeach</div>
@endif

<form method="post" action="{{ route('conductor.mantenimiento.guardar', $vehiculo) }}" enctype="multipart/form-data" id="form-mtto">
    @csrf
    @if ($planes->isNotEmpty())
        <div class="tarjeta">
            <label for="plan_accion_id">¿Atiende un plan de acción?</label>
            <select id="plan_accion_id" name="plan_accion_id">
                <option value="">No</option>
                @foreach ($planes as $p)
                    <option value="{{ $p->id }}" @selected((int) old('plan_accion_id', $plan?->id) === $p->id)>{{ $p->numero }} · {{ \Illuminate\Support\Str::limit($p->titulo, 60) }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="tarjeta">
        <label>Tipo</label>
        <div class="opciones2">
            @php $tipo = old('tipo', $plan ? ($plan->origen->value === 'preventivo' ? 'preventivo' : 'correctivo') : null); @endphp
            <input type="radio" id="t-prev" name="tipo" value="preventivo" @checked($tipo === 'preventivo') required><label for="t-prev">Preventivo</label>
            <input type="radio" id="t-corr" name="tipo" value="correctivo" @checked($tipo === 'correctivo')><label for="t-corr">Correctivo</label>
        </div>
        <div class="campo" style="margin-top:14px">
            <label for="accion">Acción</label>
            <select id="accion" name="accion" required>
                <option value="">Elija…</option>
                @foreach ($acciones as $a)<option value="{{ $a->value }}" @selected(old('accion') === $a->value)>{{ $a->getLabel() }}</option>@endforeach
            </select>
        </div>
        <div class="fila" style="gap:10px; align-items:flex-start">
            <div class="campo" style="flex:1"><label for="fecha">Fecha</label><input type="date" id="fecha" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
            <div class="campo" style="flex:1"><label for="kilometraje">Kilometraje</label><input type="number" id="kilometraje" name="kilometraje" inputmode="numeric" min="0" value="{{ old('kilometraje') }}"></div>
        </div>
        <div class="campo">
            <label for="actividad">¿Qué se hizo?</label>
            <textarea id="actividad" name="actividad" rows="3" required placeholder="Ej: cambio de aceite y filtro">{{ old('actividad') }}</textarea>
        </div>
        @if ($plan)
            <input type="hidden" name="problema" value="{{ collect($plan->hallazgos ?? [])->pluck('descripcion')->implode('; ') }}">
        @endif
        <div class="fila" style="gap:10px; align-items:flex-start">
            <div class="campo" style="flex:1"><label for="taller">Taller</label><input type="text" id="taller" name="taller" value="{{ old('taller') }}" style="font-size:16px; letter-spacing:0; text-align:left; font-family:inherit"></div>
            <div class="campo" style="flex:1"><label for="valor">Valor</label><input type="number" id="valor" name="valor" inputmode="numeric" min="0" value="{{ old('valor') }}"></div>
        </div>
    </div>

    @if ($rutinas->isNotEmpty())
        <div class="tarjeta">
            <label>Rutinas preventivas que se hicieron (opcional)</label>
            @foreach ($rutinas as $f)
                <label class="check" style="margin:8px 0">
                    <input type="checkbox" name="rutinas[]" value="{{ $f['rutina']->id }}" @checked(in_array($f['rutina']->id, (array) old('rutinas', $plan?->rutina_mantenimiento_id ? [$plan->rutina_mantenimiento_id] : [])))>
                    <span>{{ $f['rutina']->nombre }} <span class="tenue">({{ $f['rutina']->frecuencia }})</span></span>
                </label>
            @endforeach
            <p class="nota">Si marca rutinas, escriba el kilometraje.</p>
        </div>
    @endif

    <div class="tarjeta">
        <label>Evidencia (obligatoria)</label>
        <label class="archivo" for="archivos">
            <strong style="color:var(--naranja)">📷 Tomar foto o subir archivo</strong><br>
            <span class="tenue">Factura, fotos del trabajo. PDF o imagen, hasta 10 MB cada uno.</span>
            <input type="file" id="archivos" name="archivos[]" accept="image/*,application/pdf" multiple>
        </label>
        <div id="lista-archivos"></div>
        <div class="botones"><button class="boton" type="submit">Registrar mantenimiento</button></div>
    </div>
</form>
@endsection
@push('scripts')
<script>
(function () {
    var input = document.getElementById('archivos'), lista = document.getElementById('lista-archivos');
    var acumulados = new DataTransfer();
    input.addEventListener('change', function () {
        // Permite agregar fotos en varias tandas (p. ej. una foto tras otra con la cámara).
        Array.prototype.forEach.call(input.files, function (f) { acumulados.items.add(f); });
        input.files = acumulados.files;
        lista.innerHTML = Array.prototype.map.call(acumulados.files, function (f) {
            return '✓ ' + f.name + ' (' + Math.max(1, Math.round(f.size / 1024)) + ' KB)';
        }).join('<br>');
    });
    document.getElementById('form-mtto').addEventListener('submit', function (e) {
        if (!input.files.length) { e.preventDefault(); lista.innerHTML = '<span style="color:#b91c1c">Adjunte al menos una foto o la factura.</span>'; }
    });
})();
</script>
@endpush
