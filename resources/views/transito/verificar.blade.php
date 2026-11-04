@extends('firma.layout')
@php
    $empresa = \App\Models\Empresa::find($c->empresa_id);
    $d = $c->detalle();
    $ref = mb_strlen($c->referencia) > 4 ? str_repeat('•', mb_strlen($c->referencia) - 4) . mb_substr($c->referencia, -4) : $c->referencia;
    $resultado = match ($d['clase']) {
        'comparendos' => $d['paz_y_salvo'] ? 'No registra comparendos ni multas pendientes' : $d['pendientes'] . ' comparendo(s) o multa(s) pendiente(s)',
        'licencia' => $d['sin_licencia'] ? 'Sin licencia reportada en el RUNT' : ($d['suspendida'] ? 'Licencia suspendida' : 'Licencia ' . mb_strtolower($d['estado'] ?? 'registrada')),
        default => $d['sin_registro'] ? 'Sin información en el RUNT' : 'Vehículo ' . mb_strtolower($d['estado_vehiculo'] ?? 'registrado') . ' en el RUNT',
    };
@endphp
@section('titulo', 'Constancia de consulta No. ' . $c->id)
@section('cuerpo')
<div class="barra">
    <small>Verificación · Constancia de consulta de tránsito</small>
    <strong>{{ $empresa?->razon_social }}</strong>
</div>
<div class="contenedor">
    <div class="tarjeta">
        <span class="estado ok">Constancia auténtica</span>
        <dl style="margin:14px 0 0; display:grid; gap:10px">
            <div><dt class="tenue">Consulta</dt><dd style="margin:2px 0 0"><strong>No. {{ $c->id }}</strong> · {{ $c->tipo->getLabel() }}</dd></div>
            <div><dt class="tenue">Fecha de la consulta</dt><dd style="margin:2px 0 0">{{ $c->created_at->timezone(config('app.timezone'))->format('d/m/Y h:i a') }}</dd></div>
            <div><dt class="tenue">Consultado</dt><dd style="margin:2px 0 0">{{ $ref }}</dd></div>
            <div><dt class="tenue">Resultado</dt><dd style="margin:2px 0 0"><strong>{{ $resultado }}</strong></dd></div>
        </dl>
        <p style="margin:18px 0 0"><a class="boton" href="{{ $c->urlPdf() }}" target="_blank" rel="noopener">Descargar constancia (PDF)</a></p>
    </div>
    <div class="pie">Refleja el resultado en la fecha de la consulta. No reemplaza el certificado oficial del SIMIT o el RUNT.</div>
</div>
@endsection
