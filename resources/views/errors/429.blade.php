@extends('errors::layout')
@section('codigo', '429')
@section('titulo', 'Demasiados intentos')
@section('mensaje')
    <p>Se hicieron muchos intentos en poco tiempo. Espere un minuto y vuelva a intentarlo.</p>
@endsection
