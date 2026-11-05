{{-- Manifiesto electrónico de carga. Lo arma App\Support\Pdf\PdfManifiestoCarga. --}}
@php
    $p = fn ($v) => '$ ' . number_format((float) $v, 0, ',', '.');
    $tipo = \App\Models\ManifiestoCarga::TIPOS[$m->tipo] ?? $m->tipo;
    $respCargue = \App\Models\ManifiestoCarga::RESPONSABLES_PAGO[$m->responsable_cargue] ?? '';
    $respDescargue = \App\Models\ManifiestoCarga::RESPONSABLES_PAGO[$m->responsable_descargue] ?? '';
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Manifiesto {{ $m->numeroFormateado() }}</title>
<style>
    @page { margin: 32px 40px 40px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.8px; color: #111; line-height: 1.4; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .logo { max-height: 55px; max-width: 140px; }
    h1 { font-size: 13px; margin: 0; }
    h2 { font-size: 9.5px; margin: 12px 0 4px; padding: 3px 6px; background: #1B2B3A; color: #fff; }
    .tenue { color: #555; }
    .dat td { padding: 3px 5px; border: 1px solid #c9d1da; }
    .dat .et { background: #eef1f5; font-weight: bold; width: 28%; }
    .mon td { padding: 3px 6px; border: 1px solid #c9d1da; }
    .mon .et { background: #eef1f5; font-weight: bold; }
    .mon .der { text-align: right; }
    .lista th, .lista td { border: 1px solid #9aa5b1; padding: 3px 5px; font-size: 8px; }
    .lista th { background: #1B2B3A; color: #fff; text-align: left; }
    .anulado { padding: 6px; background: #fef2f2; border: 2px solid #b91c1c; color: #7f1d1d; font-weight: bold; text-align: center; }
    .estado { padding: 4px 8px; margin-top: 4px; font-size: 8.5px; display: inline-block; }
    .ok { background: #ecfdf5; border: 1px solid #15803d; color: #14532d; }
    .pend { background: #fef3c7; border: 1px solid #ca8a04; color: #78350f; }
    .nota { margin-top: 12px; font-size: 7.5px; color: #555; border-top: 1px solid #c9d1da; padding-top: 5px; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 22%">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
        <td style="padding-left: 6px">
            <div style="font-weight: bold; font-size: 10.5px">{{ mb_strtoupper($empresa->razon_social) }}</div>
            <div class="tenue">NIT {{ $empresa->nit }}{{ $empresa->dv !== null ? '-' . $empresa->dv : '' }}</div>
            <div class="tenue">{{ collect([$empresa->direccion, $empresa->municipio?->nombre])->filter()->implode(' · ') }}</div>
        </td>
        <td style="width: 24%; text-align: right">
            <h1>MANIFIESTO ELECTRÓNICO</h1>
            <h1>DE CARGA</h1>
            <div><strong>No. {{ $m->numeroFormateado() }}</strong> · {{ $tipo }}</div>
            <div class="tenue">{{ $m->fecha_expedicion->format('d/m/Y') }}</div>
        </td>
        <td style="width: 18%; text-align: right"><img src="{{ $qr }}" alt="" style="width: 68px"><div class="tenue" style="font-size: 7px">Verifique</div></td>
    </tr>
</table>

@if ($m->anulado())<div class="anulado" style="margin: 8px 0">ANULADO el {{ $m->anulado_at->format('d/m/Y') }}{{ $m->motivo_anulacion ? ' · ' . ($m->motivo_anulacion === 'D' ? 'Error en la digitación' : 'Cancelación del servicio') : '' }}</div>@endif

@if ($reg = $m->ultimoRegistroRndc)
    <div class="estado {{ $reg->estado === 'error' ? 'pend' : 'ok' }}">
        {{ $reg->estado === 'error' ? 'RNDC: pendiente de corregir errores' : 'RNDC: registrado' }}{{ $reg->ingresoid ? ' · radicado No. ' . $reg->ingresoid : '' }}
    </div>
@else
    <div class="estado pend">RNDC: sin registrar</div>
@endif

<h2>Datos del viaje</h2>
<table>
    <tr>
        <td style="width: 50%; padding-right: 5px">
            <table class="dat">
                <tr><td class="et">Origen</td><td>{{ $m->origen?->nombre_completo }}</td></tr>
                <tr><td class="et">Destino</td><td>{{ $m->destino?->nombre_completo }}</td></tr>
                <tr><td class="et">Vehículo</td><td><strong>{{ $m->vehiculo?->placa }}</strong>{{ $m->vehiculo?->marca?->nombre ? ' · ' . $m->vehiculo->marca->nombre : '' }}{{ $m->vehiculo?->modelo ? ' ' . $m->vehiculo->modelo : '' }}</td></tr>
                <tr><td class="et">Remolque</td><td>{{ $m->remolque?->placa ?? '—' }}</td></tr>
            </table>
        </td>
        <td style="width: 50%; padding-left: 5px">
            <table class="dat">
                <tr><td class="et">Conductor</td><td>{{ $m->conductor?->nombre_completo }}</td></tr>
                <tr><td class="et">Documento</td><td>{{ $m->conductor?->tipoIdentificacion?->codigo }} {{ $m->conductor?->documento }}</td></tr>
                <tr><td class="et">Titular</td><td>{{ $titular?->nombre_completo ?? '—' }}</td></tr>
                <tr><td class="et">Documento</td><td>{{ $titular?->documento ?? '' }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<h2>Remesas asociadas</h2>
<table class="lista">
    <tr><th style="width: 12%">Remesa</th><th>Producto</th><th class="der" style="width: 12%; text-align: right">Cantidad</th><th style="width: 24%">Remitente</th><th style="width: 24%">Destinatario</th></tr>
    @forelse ($m->remesas as $r)
        <tr>
            <td>{{ $r->numeroFormateado() }}</td>
            <td>{{ $r->producto_descripcion }}<div class="tenue">{{ \App\Models\RemesaCarga::OPERACIONES[$r->operacion] ?? '' }}</div></td>
            <td style="text-align: right">{{ $r->cantidad ? number_format($r->cantidad, 0, ',', '.') . ' ' . ($r->unidad === 2 ? 'gal' : 'kg') : '—' }}</td>
            <td>{{ $r->remitente?->nombre_completo }}</td>
            <td>{{ $r->destinatario?->nombre_completo }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="tenue">Sin remesas asociadas.</td></tr>
    @endforelse
</table>

<h2>Valores</h2>
<table class="mon">
    <tr><td class="et">Valor del flete pactado</td><td class="der">{{ $p($m->valor_flete) }}</td>
        <td class="et">Responsable del cargue</td><td>{{ $respCargue }}</td></tr>
    <tr><td class="et">Retención fuente ({{ rtrim(rtrim(number_format($m->retencion_fuente_pct, 2), '0'), '.') }} %)</td><td class="der">{{ $p($retefuente) }}</td>
        <td class="et">Responsable del descargue</td><td>{{ $respDescargue }}</td></tr>
    <tr><td class="et">Reteica ({{ rtrim(rtrim(number_format($m->reteica_por_mil, 2), '0'), '.') }} x1000)</td><td class="der">{{ $p($reteica) }}</td>
        <td class="et">Lugar de pago del saldo</td><td>{{ $m->municipioPago?->nombre_completo ?? $m->destino?->nombre_completo }}</td></tr>
    <tr><td class="et">Anticipo</td><td class="der">{{ $p($m->anticipo) }}</td>
        <td class="et">Fecha de pago del saldo</td><td>{{ ($m->fecha_pago_saldo ?? $m->fecha_expedicion)->format('d/m/Y') }}</td></tr>
    <tr><td class="et" style="background: #1B2B3A; color: #fff">SALDO</td><td class="der" style="background: #1B2B3A; color: #fff; font-weight: bold">{{ $p($m->saldo()) }}</td>
        <td class="et">SICE-TAC mínimo</td><td>{{ $m->valor_minimo_sicetac ? $p($m->valor_minimo_sicetac) : 'No consultado' }}</td></tr>
</table>

@if ($m->observaciones)<h2>Observaciones</h2><div>{{ $m->observaciones }}</div>@endif

<div class="nota">
    Manifiesto electrónico de carga (Resolución 20263040016075 de 2026, RNDC). Consérvese en físico o en digital durante el viaje; verifíquese su autenticidad con el QR.
    Su mérito ejecutivo lo confiere el radicado del RNDC.
</div>
</body>
</html>
