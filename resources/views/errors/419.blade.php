@extends('errors::layout')
@section('codigo', '419')
@section('titulo', 'Su sesión venció')
@section('mensaje')
    <p>Por seguridad, la página estuvo abierta mucho tiempo sin actividad. Vuelva a cargarla e intente de nuevo; si se cerró la sesión, ingrese otra vez.</p>
@endsection
@section('destino', 'javascript:location.reload()')
@section('destino_texto', 'Recargar la página')
