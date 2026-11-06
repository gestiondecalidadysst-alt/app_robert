@extends('conductor.layout')

@section('titulo', 'Sin conexión')

@section('cuerpo')
    <div class="tarjeta" style="text-align:center">
        <div style="font-size:40px; line-height:1; margin:6px 0 10px">📡</div>
        <h1 style="margin:0 0 8px">Sin conexión</h1>
        <p class="tenue" style="margin:0 0 14px">No hay señal en este momento. Revisa tus datos o el wifi y vuelve a intentar.</p>
        <button class="boton" onclick="location.reload()">Reintentar</button>
    </div>
@endsection
