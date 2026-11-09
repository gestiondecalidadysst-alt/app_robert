@extends('conductor.layout')
@section('titulo', 'Pasajeros')
@section('cuerpo')
@include('conductor.partes.menu')

<article class="tarjeta">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
        <div>
            <h2 style="margin:0;font-size:18px">Listado de pasajeros</h2>
            <p class="tenue" style="margin:4px 0 0">
                FUEC {{ $fuec->numero }}<br>
                {{ $fuec->fecha_inicio?->format('d/m/Y') }}@if ($fuec->fecha_fin && ! $fuec->fecha_fin->isSameDay($fuec->fecha_inicio)) al {{ $fuec->fecha_fin->format('d/m/Y') }}@endif
            </p>
        </div>
        <span class="chip" style="background:{{ $fuec->listadoCerrado() ? '#dcfce7' : '#fef3c7' }};color:{{ $fuec->listadoCerrado() ? '#166534' : '#92400e' }};padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700;white-space:nowrap">
            {{ $fuec->listadoCerrado() ? 'Cerrado' : 'Abierto' }} · {{ $fuec->pasajeros->count() }}
        </span>
    </div>

    @if (! $fuec->listadoCerrado())
        <p class="tenue" style="margin:12px 0 0;font-size:13px">El listado aún está abierto: la empresa puede cerrarlo cuando esté completo.</p>
    @endif
</article>

<article class="tarjeta" style="padding:0;overflow:hidden">
    @forelse ($fuec->pasajeros as $i => $p)
        <div style="display:flex;gap:12px;align-items:center;padding:13px 16px;{{ $i > 0 ? 'border-top:1px solid var(--borde)' : '' }}">
            <div style="flex:0 0 26px;height:26px;border-radius:50%;background:#eef2ff;color:#3730a3;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700">{{ $i + 1 }}</div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:700">{{ $p->nombre }}</div>
                <div class="tenue" style="font-size:13px">
                    {{ $p->tipo_documento }} {{ $p->documento }}@if ($p->telefono) · {{ $p->telefono }}@endif
                    @if ($p->es_menor)<br><span style="color:#92400e;font-weight:600">Menor</span> · acudiente: {{ $p->acudiente_nombre }} ({{ $p->acudiente_documento }})@endif
                </div>
            </div>
        </div>
    @empty
        <div style="padding:22px 16px;text-align:center" class="tenue">Aún no hay pasajeros registrados.</div>
    @endforelse
</article>

<article class="tarjeta" style="text-align:center">
    <p class="tenue" style="margin:0 0 12px;font-size:13px">Para una revisión, muestre este código: la autoridad lo escanea y verifica el listado.</p>
    <img src="{{ $qr }}" alt="Código QR de verificación" style="width:170px;height:170px;border-radius:10px">
</article>

<div class="botones">
    <a class="boton sec" href="{{ route('conductor.inicio') }}">Volver</a>
</div>
@endsection
