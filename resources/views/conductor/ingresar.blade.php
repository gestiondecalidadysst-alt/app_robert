@extends('conductor.layout')
@section('titulo', 'Ingresar')
@section('cuerpo')
<div class="tarjeta" style="margin-top:24px">
    <h1>Ingresar</h1>
    <p class="tenue" style="margin-top:-6px">Use su número de documento y la clave que le dio la empresa.</p>
    @if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('conductor.ingresar.enviar') }}">
        @csrf
        <div class="campo">
            <label for="documento">Número de documento</label>
            <input type="text" id="documento" name="documento" inputmode="numeric" autocomplete="username" value="{{ old('documento') }}" required autofocus>
        </div>
        <div class="campo">
            <label for="clave">Clave</label>
            <input type="password" id="clave" name="clave" autocomplete="current-password" required>
        </div>
        <button class="boton" type="submit">Ingresar</button>
    </form>
    <p class="nota" style="margin-top:14px">¿Olvidó la clave? Pídale a la empresa que se la restablezca.</p>
</div>
@endsection
