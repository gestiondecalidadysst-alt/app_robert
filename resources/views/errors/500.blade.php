@extends('errors::layout')
@section('codigo', '500')
@section('titulo', 'Algo falló de nuestro lado')
@section('mensaje')
    <p>No fue culpa suya: ocurrió un error inesperado y ya quedó registrado. El equipo de soporte recibió el aviso.</p>
    <p>Si necesita reportarlo, comparta este código:</p>
    <div class="ref">{{ \App\Support\Sistema\Referencia::actual() }}</div>
    <p>Puede volver e intentarlo de nuevo en unos minutos.</p>
@endsection
