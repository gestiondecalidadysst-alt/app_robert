@extends('conductor.layout')
@section('titulo', 'Mis preoperacionales')
@section('cuerpo')
<nav class="menu">
    <a href="{{ route('conductor.inicio') }}">Servicios</a>
    <a class="activo" href="{{ route('conductor.historial') }}">Mis preoperacionales</a>
</nav>
<h1>Mis preoperacionales</h1>
@forelse ($inspecciones as $i)
    <a class="tarjeta fila" href="{{ route('conductor.inspeccion.pdf', $i) }}" target="_blank" rel="noopener" style="text-decoration:none; color:inherit; display:flex">
        <div>
            <div style="font-weight:700">{{ $i->placa }} · {{ $i->fecha->format('d/m/Y') }}</div>
            <div class="tenue" style="font-size:13px">{{ $i->created_at->format('h:i a') }} · {{ count($i->fallas()) }} {{ count($i->fallas()) === 1 ? 'falla' : 'fallas' }} · Ver PDF</div>
        </div>
        <span class="estado {{ $i->apta() ? 'ok' : 'mal' }}">{{ $i->resultado->getLabel() }}</span>
    </a>
@empty
    <div class="tarjeta"><p class="tenue" style="margin:0">Aún no ha registrado preoperacionales.</p></div>
@endforelse
@endsection
