@extends('firma.layout')
@php
    $estado = $documento->estadoFirma();
    $clase = match ($estado->value) { 'firmado' => 'ok', 'anulado', 'vencido' => 'mal', 'emitido' => 'gris', default => 'info' };
    $e = $documento->datos['empresa'] ?? [];
    $v = $documento->datos['valores'] ?? [];
@endphp
@section('titulo', 'Verificación de contrato')
@section('cuerpo')
<div class="barra">
    <small>Verificación de contrato</small>
    <strong>{{ $e['razon_social'] ?? '' }}</strong>
</div>
<div class="contenedor">
    <div class="tarjeta">
        <span class="estado {{ $clase }}">{{ $estado->getLabel() }}</span>
        <dl style="margin:14px 0 0; display:grid; gap:10px">
            <div><dt class="tenue">Contrato No.</dt><dd style="margin:2px 0 0">{{ $v['contrato.numero'] ?? '' }}</dd></div>
            <div><dt class="tenue">Contratante</dt><dd style="margin:2px 0 0">{{ $v['contratante.nombre'] ?? '' }}</dd></div>
            <div><dt class="tenue">Emitido</dt><dd style="margin:2px 0 0">{{ $documento->created_at->format('d/m/Y') }} · plantilla {{ $documento->codigo_formato ? $documento->codigo_formato.' ' : '' }}versión {{ str_pad((string) $documento->version_plantilla, 2, '0', STR_PAD_LEFT) }}</dd></div>
            @if ($documento->firmado())
                <div><dt class="tenue">Firmado</dt><dd style="margin:2px 0 0">{{ $documento->firmante_nombre }} · {{ $documento->firmado_at->format('d/m/Y h:i a') }} · código por {{ $documento->firma_canal_codigo === 'sms' ? 'SMS' : 'correo' }} a {{ $documento->firma_destino_codigo }}</dd></div>
                <div><dt class="tenue">Huella del PDF firmado (SHA-256)</dt><dd style="margin:2px 0 0; font-family:ui-monospace,monospace; font-size:12px; word-break:break-all">{{ $documento->huella_pdf }}</dd></div>
            @endif
            @if ($documento->anulado())
                <div><dt class="tenue">Anulado</dt><dd style="margin:2px 0 0; color:#b91c1c">{{ $documento->anulado_at->format('d/m/Y') }}</dd></div>
            @endif
        </dl>
    </div>

    @if ($documento->firmado())
        <div class="tarjeta">
            <h2 style="margin-top:0; font-size:17px">¿El PDF que tiene es el original?</h2>
            <p class="tenue" style="margin-top:0">Súbalo y lo comparamos con el contrato firmado. El archivo no se guarda.</p>
            @if ($resultado === true)
                <div class="aviso"><strong>Coincide.</strong> El archivo es idéntico al contrato firmado.</div>
            @elseif ($resultado === false)
                <div class="error"><strong>No coincide.</strong> El archivo fue modificado o no corresponde a este contrato.</div>
            @endif
            @if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
            <form method="post" enctype="multipart/form-data" action="{{ route('contrato.verificar.archivo', $documento->codigo_verificacion) }}">
                @csrf
                <input type="file" name="archivo" accept="application/pdf" required style="margin-bottom:12px; width:100%">
                <button class="boton" type="submit">Comprobar archivo</button>
            </form>
        </div>
    @endif

    <div class="pie">Consultado el {{ now()->format('d/m/Y h:i a') }}.</div>
</div>
@endsection
