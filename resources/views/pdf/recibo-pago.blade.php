{{--
    Recibo de caja de un pago de contrato (media carta horizontal). Lo arma App\Support\Pdf\PdfRecibo::recibo.
--}}
@php
    $pesos = fn ($n) => '$ ' . number_format((float) $n, 0, ',', '.');
    $tipo = $pago->tipo();
    $concepto = match ($tipo) {
        \App\Models\PagoContrato::PAGO_TOTAL => 'Pago total del contrato',
        \App\Models\PagoContrato::ABONO_FINAL => 'Abono final (cancela el saldo) del contrato',
        default => 'Abono al contrato',
    };
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo {{ $pago->numero_recibo }}</title>
<style>
    @page { margin: 22px 28px 20px 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.4px; color: #111; line-height: 1.35; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    .logo { max-height: 46px; max-width: 120px; }
    .empresa { font-weight: bold; font-size: 10px; }
    .tenue { color: #555; }
    .caja { border: 1.2px solid #1B2B3A; border-radius: 4px; text-align: center; padding: 5px 6px; }
    .caja .titulo { font-size: 8px; font-weight: bold; letter-spacing: .5px; }
    .caja .numero { font-size: 14px; font-weight: bold; color: #B91C1C; }
    .campos { margin-top: 8px; border: 1px solid #9aa5b1; }
    .campos td { padding: 4px 6px; border-bottom: 1px solid #d5dbe2; }
    .campos .et { width: 19%; background: #eef1f5; font-weight: bold; }
    .valor { font-size: 12px; font-weight: bold; }
    .saldos { margin-top: 7px; }
    .saldos th, .saldos td { border: 1px solid #9aa5b1; padding: 3px 5px; text-align: center; }
    .saldos th { background: #1B2B3A; color: #fff; font-size: 7.4px; }
    .saldos td { font-size: 9px; }
    .destacado { background: #FFF7ED; font-weight: bold; }
    .sello { margin-top: 6px; text-align: center; font-weight: bold; color: #15803D; border: 1.4px solid #15803D; padding: 3px; font-size: 9px; letter-spacing: .4px; }
    .firmas { margin-top: 18px; }
    .firmas td { width: 36%; padding-right: 22px; font-size: 7.6px; }
    .linea { border-top: 1px solid #111; padding-top: 2px; }
    .qr { width: 62px; }
    .marca { position: fixed; top: 110px; left: 70px; font-size: 76px; color: rgba(200, 0, 0, .18); transform: rotate(-18deg); font-weight: bold; }
    .pie { margin-top: 5px; font-size: 6.6px; color: #555; text-align: center; }
</style>
</head>
<body>
@if ($pago->anulado())
    <div class="marca">ANULADO</div>
@endif

<table>
    <tr>
        <td style="width: 21%">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
        <td style="width: 52%; padding-left: 6px">
            <div class="empresa">{{ mb_strtoupper($empresa->razon_social) }}</div>
            @if ($v['empresa.nit'])<div>NIT {{ $v['empresa.nit'] }}</div>@endif
            <div class="tenue">{{ collect([$empresa->direccion, $v['empresa.ciudad']])->filter()->implode(' · ') }}</div>
            <div class="tenue">{{ collect([$empresa->telefono ? 'Tel. ' . $empresa->telefono : null, $empresa->email])->filter()->implode(' · ') }}</div>
        </td>
        <td style="width: 27%">
            <div class="caja">
                <div class="titulo">RECIBO DE CAJA</div>
                <div class="numero">No. {{ $pago->numero_recibo }}</div>
                <div>Fecha: <strong>{{ $pago->fecha->format('d/m/Y') }}</strong></div>
            </div>
        </td>
    </tr>
</table>

<table class="campos">
    <tr><td class="et">Recibimos de</td><td colspan="3"><strong>{{ $v['contratante.nombre'] }}</strong>@if ($v['contratante.identificacion']) · {{ $v['contratante.identificacion'] }}@endif</td></tr>
    <tr><td class="et">La suma de</td><td colspan="3"><span class="valor">{{ $pesos($pago->valor) }}</span> &nbsp; ({{ $letras }})</td></tr>
    <tr><td class="et">Por concepto de</td><td colspan="3">{{ $concepto }} de prestación de servicios de transporte No. <strong>{{ $contrato->numero_formateado }}</strong>
        ({{ $v['contrato.objeto'] ?? '' }}, {{ $contrato->vigencia }})</td></tr>
    <tr>
        <td class="et">Forma de pago</td><td style="width: 31%">{{ $pago->metodo?->getLabel() }}</td>
        <td class="et">Referencia</td><td>{{ $pago->referencia ?: '—' }}</td>
    </tr>
    @if ($pago->observacion)
        <tr><td class="et">Observación</td><td colspan="3">{{ $pago->observacion }}</td></tr>
    @endif
</table>

<table class="saldos">
    <tr><th>Valor del contrato</th><th>Abonado antes</th><th>Este pago</th><th>Saldo pendiente</th></tr>
    <tr>
        <td>{{ $pesos($contrato->valor) }}</td>
        <td>{{ $pesos($abonadoAntes) }}</td>
        <td class="destacado">{{ $pesos($pago->valor) }}</td>
        <td><strong>{{ $pesos($pago->saldo_despues) }}</strong></td>
    </tr>
</table>

@if ($pago->anulado())
    <div class="sello" style="color:#B91C1C;border-color:#B91C1C">RECIBO ANULADO EL {{ $pago->anulado_at->format('d/m/Y') }}: {{ mb_strtoupper($pago->motivo_anulacion) }}</div>
@elseif ((float) $pago->saldo_despues <= 0)
    <div class="sello">CONTRATO PAGADO EN SU TOTALIDAD</div>
@endif

<table class="firmas">
    <tr>
        <td><div style="height: 26px"></div><div class="linea"><strong>Recibido por:</strong> {{ $pago->usuario?->name ?? '' }}<br>{{ mb_strtoupper($empresa->nombre_comercial ?: $empresa->razon_social) }}</div></td>
        <td><div style="height: 26px"></div><div class="linea"><strong>Entregado por (cliente)</strong><br>C.C./NIT:</div></td>
        <td style="width: 28%; padding-right: 0; text-align: right">
            <img class="qr" src="{{ $qr }}" alt=""><br>
            <span class="tenue" style="font-size: 6.4px">Verifique este recibo escaneando el código</span>
        </td>
    </tr>
</table>

<div class="pie">Documento generado por el sistema el {{ now()->format('d/m/Y h:i a') }}. Este recibo no es una factura de venta.</div>
</body>
</html>
