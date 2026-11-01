@extends('conductor.layout')
@section('titulo', 'Cambiar clave')
@section('cuerpo')
<div class="tarjeta">
    <h1>{{ $obligatorio ? 'Cree su clave personal' : 'Cambiar clave' }}</h1>
    @if ($obligatorio)<p class="tenue" style="margin-top:-6px">La clave que le dio la empresa es temporal. Cree una que solo usted conozca: con ella se registran sus preoperacionales.</p>@endif
    @if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('conductor.clave.guardar') }}">
        @csrf
        <div class="campo">
            <label for="actual">Clave {{ $obligatorio ? 'temporal' : 'actual' }}</label>
            <input type="password" id="actual" name="actual" autocomplete="current-password" required>
        </div>
        <div class="campo">
            <label for="clave">Clave nueva</label>
            <input type="password" id="clave" name="clave" autocomplete="new-password" minlength="8" required>
            <p class="nota">Mínimo 8 caracteres, con letras y números.</p>
        </div>
        <div class="campo">
            <label for="clave_confirmation">Repita la clave nueva</label>
            <input type="password" id="clave_confirmation" name="clave_confirmation" autocomplete="new-password" minlength="8" required>
        </div>
        <button class="boton" type="submit">Guardar clave</button>
    </form>
    @unless ($obligatorio)<p style="margin-top:12px"><a class="enlace" href="{{ route('conductor.inicio') }}">Volver</a></p>@endunless
</div>
@endsection
