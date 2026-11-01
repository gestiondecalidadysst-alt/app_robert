@extends('firma.layout')
@php
    $d = $i->datos;
    $doc = (string) ($d['conductor']['documento'] ?? '');
@endphp
@section('titulo', 'Verificación de inspección')
@section('cuerpo')
<div class="barra">
    <small>Verificación · {{ $i->tipo->corto() }}</small>
    <strong>{{ $d['empresa']['razon_social'] ?? '' }}</strong>
</div>
<div class="contenedor">
    <div class="tarjeta">
        <span class="estado {{ $i->apta() ? 'ok' : 'mal' }}">{{ $i->resultado->getLabel() }}</span>
        <dl style="margin:14px 0 0; display:grid; gap:10px">
            <div><dt class="tenue">Fecha y hora</dt><dd style="margin:2px 0 0">{{ $i->created_at->format('d/m/Y h:i a') }}</dd></div>
            <div><dt class="tenue">Vehículo</dt><dd style="margin:2px 0 0">{{ $d['vehiculo']['placa'] ?? '' }}{{ ! empty($d['vehiculo']['numero_interno']) ? ' · No. interno '.$d['vehiculo']['numero_interno'] : '' }}</dd></div>
            @if (! empty($d['conductor']))
                <div><dt class="tenue">Conductor</dt><dd style="margin:2px 0 0">{{ $d['conductor']['nombre'] }} · documento terminado en {{ substr($doc, -4) }}</dd></div>
            @endif
            <div><dt class="tenue">Ítems revisados</dt><dd style="margin:2px 0 0">{{ count($i->respuestas) }} · {{ count($i->fallas()) }} no cumple{{ count($i->fallas()) === 1 ? '' : 'n' }}</dd></div>
        </dl>
    </div>
    <div class="pie">Consultado el {{ now()->format('d/m/Y h:i a') }}.</div>
</div>
@endsection
