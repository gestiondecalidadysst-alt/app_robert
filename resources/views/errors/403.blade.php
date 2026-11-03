@extends('errors::layout')
@section('codigo', '403')
@section('titulo', 'No tiene permiso para ver esto')
@section('mensaje')
    <p>Su usuario no tiene acceso a esta pantalla o a esta acción. Si cree que debería tenerlo, pídale al administrador de su empresa que revise su rol en <strong>Configuración → Roles y permisos</strong>.</p>
@endsection
