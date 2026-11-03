@extends('firma.layout')
@php
    $empresa = \App\Models\Empresa::find($p->empresa_id);
    $pesos = fn ($n) => '$ ' . number_format((float) $n, 0, ',', '.');
@endphp
@section('titulo', 'Recibo ' . $p->numero_recibo)
@section('cuerpo')
<div class="barra">
    <small>Verificación · Recibo de caja</small>
    <strong>{{ $empresa?->razon_social }}</strong>
</div>
<div class="contenedor">
    <div class="tarjeta">
        <span class="estado {{ $p->anulado() ? 'mal' : 'ok' }}">{{ $p->anulado() ? 'Anulado' : 'Válido' }}</span>
        <dl style="margin:14px 0 0; display:grid; gap:10px">
            <div><dt class="tenue">Recibo</dt><dd style="margin:2px 0 0"><strong>No. {{ $p->numero_recibo }}</strong> · {{ $p->fecha->format('d/m/Y') }}</dd></div>
            <div><dt class="tenue">Concepto</dt><dd style="margin:2px 0 0">{{ $p->tipoEtiqueta() }} · contrato No. {{ $p->contrato?->numero_formateado }}</dd></div>
            <div><dt class="tenue">Valor</dt><dd style="margin:2px 0 0"><strong>{{ $pesos($p->valor) }}</strong> · {{ $p->metodo?->getLabel() }}</dd></div>
            @unless ($p->anulado())
                <div><dt class="tenue">Saldo del contrato después de este pago</dt><dd style="margin:2px 0 0">{{ $pesos($p->saldo_despues) }}</dd></div>
            @endunless
        </dl>
        <p style="margin:18px 0 0"><a class="boton" href="{{ $p->urlPdf() }}" target="_blank" rel="noopener">Descargar recibo (PDF)</a></p>
    </div>
    <div class="pie">Consultado el {{ now()->format('d/m/Y h:i a') }}.</div>
</div>
@endsection
