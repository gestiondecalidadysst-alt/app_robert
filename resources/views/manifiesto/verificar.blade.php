@extends('firma.layout')
@php
    $empresa = \App\Models\Empresa::find($m->empresa_id);
    $pesos = fn ($n) => '$ ' . number_format((float) $n, 0, ',', '.');
@endphp
@section('titulo', 'Manifiesto ' . $m->numeroFormateado())
@section('cuerpo')
<div class="barra">
    <small>Verificación · Manifiesto electrónico de carga</small>
    <strong>{{ $empresa?->razon_social }}</strong>
</div>
<div class="contenedor">
    <div class="tarjeta">
        <span class="estado {{ $m->anulado() ? 'mal' : 'ok' }}">{{ $m->anulado() ? 'Anulado' : 'Vigente' }}</span>
        <dl style="margin:14px 0 0; display:grid; gap:10px">
            <div><dt class="tenue">Manifiesto</dt><dd style="margin:2px 0 0"><strong>No. {{ $m->numeroFormateado() }}</strong> · {{ $m->fecha_expedicion->format('d/m/Y') }}</dd></div>
            <div><dt class="tenue">Ruta</dt><dd style="margin:2px 0 0">{{ $m->origen?->nombre }} → {{ $m->destino?->nombre }}</dd></div>
            <div><dt class="tenue">Vehículo</dt><dd style="margin:2px 0 0">{{ $m->vehiculo?->placa }}{{ $m->remolque ? ' · ' . $m->remolque->placa : '' }}</dd></div>
            <div><dt class="tenue">Valor del flete</dt><dd style="margin:2px 0 0"><strong>{{ $pesos($m->valor_flete) }}</strong></dd></div>
            @if ($r = $m->ultimoRegistroRndc)<div><dt class="tenue">RNDC</dt><dd style="margin:2px 0 0">{{ $r->estado === 'error' ? 'Con errores' : ('Radicado No. ' . $r->ingresoid) }}</dd></div>@endif
        </dl>
        <p style="margin:18px 0 0"><a class="boton" href="{{ $m->urlPdf() }}" target="_blank" rel="noopener">Descargar manifiesto (PDF)</a></p>
    </div>
    <div class="pie">Consultado el {{ now()->format('d/m/Y h:i a') }}.</div>
</div>
@endsection
