@extends('errors::layout')
@section('codigo', '503')
@section('titulo', 'Estamos actualizando la plataforma')
@section('mensaje')
    <p>La plataforma está en mantenimiento o reiniciándose después de una actualización. Normalmente toma uno o dos minutos.</p>
    <div class="ref">{{ \App\Support\Sistema\Referencia::actual() }}</div>
@endsection
@section('destino', 'javascript:location.reload()')
@section('destino_texto', 'Intentar de nuevo')
