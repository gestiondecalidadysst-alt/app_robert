{{--
    Estado de cuenta del contrato; con saldo cero es la constancia de pago total (paz y salvo).
    Lo arma App\Support\Pdf\PdfRecibo::estadoCuenta.
--}}
@php
    $pesos = fn ($n) => '$ ' . number_format((float) $n, 0, ',', '.');
    $titulo = $pazYSalvo ? 'PAZ Y SALVO · CONSTANCIA DE PAGO TOTAL' : 'ESTADO DE CUENTA DEL CONTRATO';
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $titulo }} {{ $contrato->numero_formateado }}</title>
<style>
    @page { margin: 40px 50px 46px 50px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.4px; color: #111; line-height: 1.45; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    .logo { max-height: 62px; max-width: 150px; }
    .empresa { font-weight: bold; font-size: 11px; }
    .tenue { color: #555; }
    h1 { text-align: center; font-size: 12.5px; margin: 18px 0 4px; letter-spacing: .5px; }
    .sub { text-align: center; color: #555; margin-bottom: 14px; }
    .datos td { padding: 4px 7px; border: 1px solid #c9d1da; }
    .datos .et { width: 24%; background: #eef1f5; font-weight: bold; }
    .pagos { margin-top: 14px; }
    .pagos th, .pagos td { border: 1px solid #9aa5b1; padding: 4px 6px; }
    .pagos th { background: #1B2B3A; color: #fff; font-size: 8.4px; text-align: left; }
    .der { text-align: right; }
    .anulado td { color: #9ca3af; text-decoration: line-through; }
    .totales td { font-weight: bold; background: #eef1f5; }
    .certifica { margin-top: 18px; text-align: justify; font-size: 10px; }
    .sello { margin-top: 12px; text-align: center; font-weight: bold; color: #15803D; border: 1.6px solid #15803D; padding: 6px; font-size: 11px; letter-spacing: .5px; }
    .pendiente { color: #B45309; border-color: #B45309; }
    .firma { margin-top: 36px; width: 46%; }
    .firma-img { height: 54px; max-width: 200px; }
    .linea { border-top: 1px solid #111; padding-top: 3px; }
    .pie { position: fixed; bottom: -28px; left: 0; right: 0; text-align: center; font-size: 7.4px; color: #555; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 24%">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
        <td style="padding-left: 8px">
            <div class="empresa">{{ mb_strtoupper($empresa->razon_social) }}</div>
            @if ($v['empresa.nit'])<div>NIT {{ $v['empresa.nit'] }}</div>@endif
            <div class="tenue">{{ collect([$empresa->direccion, $v['empresa.ciudad']])->filter()->implode(' · ') }}</div>
            <div class="tenue">{{ collect([$empresa->telefono ? 'Tel. ' . $empresa->telefono : null, $empresa->email])->filter()->implode(' · ') }}</div>
        </td>
    </tr>
</table>

<h1>{{ $titulo }}</h1>
<div class="sub">Contrato de prestación de servicios de transporte No. {{ $contrato->numero_formateado }} · expedido el {{ now()->format('d/m/Y') }}</div>

<table class="datos">
    <tr><td class="et">Cliente</td><td>{{ $v['contratante.nombre'] }}@if ($v['contratante.identificacion']) · {{ $v['contratante.identificacion'] }}@endif</td></tr>
    <tr><td class="et">Objeto y vigencia</td><td>{{ $v['contrato.objeto'] ?? '' }} · {{ $contrato->vigencia }}</td></tr>
    <tr><td class="et">Valor del contrato</td><td><strong>{{ $pesos($contrato->valor) }}</strong></td></tr>
    @if ($contrato->anulado())
        <tr><td class="et">Estado</td><td style="color:#B91C1C"><strong>Contrato anulado</strong></td></tr>
    @endif
</table>

<table class="pagos">
    <tr><th style="width: 15%">Recibo</th><th style="width: 13%">Fecha</th><th style="width: 15%">Tipo</th><th>Forma de pago</th><th class="der" style="width: 17%">Valor</th><th class="der" style="width: 17%">Saldo después</th></tr>
    @forelse ($pagos as $p)
        <tr @class(['anulado' => $p->anulado()])>
            <td>{{ $p->numero_recibo }}</td>
            <td>{{ $p->fecha->format('d/m/Y') }}</td>
            <td>{{ $p->anulado() ? 'Anulado' : $p->tipoEtiqueta() }}</td>
            <td>{{ $p->metodo?->getLabel() }}{{ $p->referencia ? ' · ' . $p->referencia : '' }}</td>
            <td class="der">{{ $pesos($p->valor) }}</td>
            <td class="der">{{ $p->anulado() ? '—' : $pesos($p->saldo_despues) }}</td>
        </tr>
    @empty
        <tr><td colspan="6" style="text-align:center" class="tenue">Sin pagos registrados.</td></tr>
    @endforelse
    <tr class="totales"><td colspan="4" class="der">Total pagado</td><td class="der">{{ $pesos($pagado) }}</td><td></td></tr>
    <tr class="totales"><td colspan="4" class="der">Saldo pendiente</td><td class="der">{{ $pesos($saldo) }}</td><td></td></tr>
</table>
@if ($pagos->contains(fn ($p) => $p->anulado()))
    <div class="tenue" style="margin-top: 3px; font-size: 7.8px">Los recibos tachados están anulados y no suman al total pagado.</div>
@endif

@if ($pazYSalvo)
    <div class="certifica">
        {{ mb_strtoupper($empresa->razon_social) }}@if ($v['empresa.nit']), NIT {{ $v['empresa.nit'] }},@endif hace constar que
        <strong>{{ $v['contratante.nombre'] }}</strong>@if ($v['contratante.identificacion']), identificado(a) con {{ $v['contratante.identificacion'] }},@endif
        pagó la totalidad del valor del contrato No. {{ $contrato->numero_formateado }}, es decir, <strong>{{ $letras }} ({{ $pesos($pagado) }})</strong>,
        según los recibos relacionados{{ $ultimoPago ? ", el último de ellos del " . $ultimoPago->fecha->format("d/m/Y") : "" }}. Por lo tanto, se encuentra
        <strong>a paz y salvo</strong> por concepto de este contrato.
    </div>
    <div class="sello">A PAZ Y SALVO · CONTRATO PAGADO EN SU TOTALIDAD</div>
@elseif (! $contrato->anulado())
    <div class="sello pendiente">SALDO PENDIENTE POR PAGAR: {{ $pesos($saldo) }}@if ($contrato->fecha_limite_pago) · FECHA LÍMITE {{ $contrato->fecha_limite_pago->format('d/m/Y') }}@endif</div>
@endif

<div class="firma">
    @if ($firma)<img class="firma-img" src="{{ $firma }}" alt="">@else<div style="height: 54px"></div>@endif
    <div class="linea">
        <strong>{{ $v['empresa.representante'] ?? '' }}</strong><br>
        Representante legal · {{ mb_strtoupper($empresa->nombre_comercial ?: $empresa->razon_social) }}
    </div>
</div>

<div class="pie">Generado por el sistema el {{ now()->format('d/m/Y h:i a') }}. Cada recibo puede verificarse con el código QR que lleva impreso.</div>
</body>
</html>
