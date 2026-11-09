{{--
    Cotización de transporte especial. La arma App\Support\Pdf\PdfCotizacion::generar.
    Colores y textos (incluye/equipo/validez/pie) salen de la configuración de la empresa.
--}}
@php
    $money = fn ($n) => '$ ' . number_format((float) $n, 0, ',', '.');
    $oscuro = $config['color'];
    $lima = $config['color_acento'];
    $marca = $empresa->nombre_comercial ?: $empresa->razon_social;
    $tel = collect([$empresa->telefono, $config['telefono_2']])->filter()->implode('  ·  ');
    $dir = collect([$empresa->direccion, $empresa->municipio?->nombre])->filter()->implode(' – ');
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Cotización {{ $c->numero_formateado }}</title>
<style>
    @page { margin: 156px 0 186px 0; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 0; }
    .px { padding-left: 40px; padding-right: 40px; }

    /* Encabezado fijo: banda oscura (logo + número) y, debajo, fila blanca (sello Vigilado + NIT). */
    .cab { position: fixed; top: -156px; left: 0; right: 0; height: 156px; background: #fff; }
    .cab .banda { background: {{ $oscuro }}; color: #fff; height: 96px; }
    .cab .fila1 { padding: 24px 40px 0 40px; }
    .cab .marca { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
    .cab .marca img { max-height: 42px; max-width: 230px; }
    .cab .titulo { color: {{ $lima }}; font-size: 10px; font-weight: bold; letter-spacing: 3px; }
    .cab .numero { font-size: 19px; font-weight: bold; }
    .cab .fila2 { padding: 12px 40px 0 40px; }
    .cab .escudo { height: 40px; vertical-align: middle; margin-right: 10px; }
    .cab .sello { font-size: 12px; color: #6b7280; vertical-align: middle; }
    .cab .sello b { color: {{ $oscuro }}; }
    .cab .nit { color: #6b7280; font-size: 11px; letter-spacing: 2px; font-weight: bold; }
    .cab .linea { position: absolute; left: 0; right: 0; bottom: 0; height: 5px; background: {{ $lima }}; }

    /* Pie fijo */
    .pie { position: fixed; bottom: -186px; left: 0; right: 0; height: 104px; background: {{ $oscuro }}; color: #fff; }
    .pie .tel { font-size: 18px; font-weight: bold; }
    .pie .dato { font-size: 9.5px; color: #cbd5e1; }
    .pie .red { color: {{ $lima }}; font-size: 15px; font-weight: bold; text-align: right; }

    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }

    .dest .senores { color: #9ca3af; font-size: 8px; letter-spacing: 3px; font-weight: bold; }
    .dest .nombre { font-size: 20px; font-weight: bold; color: {{ $oscuro }}; }
    .dest .fecha { color: #6b7280; font-size: 10px; text-align: right; vertical-align: middle; }
    .asunto { color: #3b6a5e; font-size: 10px; margin: 12px 0 0; line-height: 1.5; }
    .asunto strong { color: #3b6a5e; }

    .items { margin-top: 14px; }
    .items thead th { background: {{ $oscuro }}; color: #fff; font-size: 8px; letter-spacing: 2px; font-weight: bold;
        text-align: left; padding: 8px 12px; }
    .items thead th.val { text-align: right; }
    .items tbody td { padding: 6px 12px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
    .items .fecha { color: #374151; width: 17%; }
    .items .ruta { font-weight: bold; color: {{ $oscuro }}; width: 42%; }
    .items .det { color: #4b5563; width: 23%; }
    .items .val { text-align: right; font-weight: bold; width: 18%; white-space: nowrap; }
    .items tr { page-break-inside: avoid; }
    .items .descuento .ruta { color: {{ $oscuro }}; }
    .items .descuento .val { color: #374151; }

    .total { margin-top: 8px; background: {{ $lima }}; color: #fff; padding: 12px 18px; }
    .total .lbl { font-size: 11px; letter-spacing: 2px; font-weight: bold; }
    .total .num { font-size: 20px; font-weight: bold; }

    .cards { margin-top: 12px; }
    .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; }
    .card h3 { margin: 0 0 8px; font-size: 11px; letter-spacing: 1px; color: {{ $oscuro }};
        border-bottom: 2px solid {{ $lima }}; padding-bottom: 6px; }
    .card ul { margin: 0; padding: 0; list-style: none; }
    .card li { font-size: 10px; color: #374151; padding: 3px 0 3px 14px; position: relative; }
    .card li:before { content: '•'; color: {{ $lima }}; position: absolute; left: 0; font-weight: bold; }

    /* Franja de certificados: fija al pie de TODAS las páginas, justo sobre el pie. */
    .certs { position: fixed; bottom: -66px; left: 0; right: 0; height: 66px; }
    .certs .box { margin: 8px 40px 0; border: 1px solid #e5e7eb; border-radius: 8px; padding: 7px 16px; background: #fff; }
    .certs .et { color: {{ $lima }}; font-size: 8px; letter-spacing: 2px; font-weight: bold; }
    .certs .txt { color: #4b5563; font-size: 10px; margin-top: 3px; }
    .certs .logos { text-align: right; vertical-align: middle; width: 48%; }
    /* Altura fija = todos los logos quedan parejos aunque se suban de tamaños distintos. */
    .certs .logos img { height: 42px; width: auto; margin-left: 16px; vertical-align: middle; }
</style>
</head>
<body>

{{-- Encabezado --}}
<div class="cab">
    <div class="banda">
        <table class="fila1"><tr>
            <td style="width: 55%">
                @if ($logo)<span class="marca"><img src="{{ $logo }}" alt=""></span>
                @else<span class="marca">{{ mb_strtoupper($marca) }}</span>@endif
            </td>
            <td style="width: 45%; text-align: right">
                <div class="titulo">COTIZACIÓN DE TRANSPORTE ESPECIAL</div>
                <div class="numero">{{ $c->numero_formateado }}</div>
            </td>
        </tr></table>
    </div>
    <table class="fila2"><tr>
        <td style="width: 60%; vertical-align: middle">
            @if ($sello)<img src="{{ $sello }}" class="escudo" alt="">@endif<span class="sello"><b>Vigilado</b> SuperTransporte</span>
        </td>
        <td style="width: 40%; text-align: right; vertical-align: middle">
            @if ($empresa->nit_formateado)<span class="nit">NIT {{ $empresa->nit_formateado }}</span>@endif
        </td>
    </tr></table>
    <div class="linea"></div>
</div>

{{-- Pie --}}
<div class="pie">
    <table style="height: 104px"><tr>
        <td class="px" style="width: 62%; vertical-align: middle">
            <div class="tel">{{ $tel ?: '' }}</div>
            @if ($empresa->email)<div class="dato">{{ $empresa->email }}</div>@endif
            @if ($dir)<div class="dato">{{ $dir }}</div>@endif
        </td>
        <td class="px" style="width: 38%; vertical-align: middle">
            @if ($config['instagram'])<div class="red">&#64;{{ ltrim($config['instagram'], '@') }}</div>@endif
        </td>
    </tr></table>
</div>

{{-- Franja de certificados: fija al pie de todas las páginas, por encima del pie --}}
<div class="certs">
    <div class="box">
        <table><tr>
            <td style="vertical-align: middle">
                <div class="et">CERTIFICADOS POR</div>
                <div class="txt">Oferta válida por {{ $config['validez_dias'] }} días a partir de la fecha de expedición.</div>
            </td>
            <td class="logos">
                @foreach ($certificados as $logoCert)<img src="{{ $logoCert }}" alt="">@endforeach
            </td>
        </tr></table>
    </div>
</div>

{{-- Cuerpo --}}
<div class="px">
    <table class="dest"><tr>
        <td style="width: 60%">
            <div class="senores">SEÑORES</div>
            <div class="nombre">{{ mb_strtoupper($c->destinatarioNombre()) }}</div>
        </td>
        <td style="width: 40%; vertical-align: middle">
            <div class="fecha">{{ collect([$c->ciudad, $c->fecha->translatedFormat('d \d\e F \d\e Y')])->filter()->implode(', ') }}</div>
        </td>
    </tr></table>

    @if ($c->asunto)<p class="asunto"><strong>Asunto:</strong> {{ $c->asunto }}</p>@endif

    <table class="items">
        <thead><tr>
            <th>FECHA</th><th>RUTA</th><th>DURACIÓN</th><th class="val">VALOR</th>
        </tr></thead>
        <tbody>
            @foreach ($c->items as $item)
                <tr>
                    <td class="fecha">{{ $item->fecha?->translatedFormat('j \d\e F') ?: $item->fecha_texto }}</td>
                    <td class="ruta">{{ $item->ruta }}</td>
                    <td class="det">{{ $item->detalle }}</td>
                    <td class="val">{{ $money($item->valor) }}</td>
                </tr>
            @endforeach
            @if ($c->descuento > 0)
                <tr class="descuento">
                    <td class="fecha"></td><td class="ruta">Descuento comercial</td><td class="det"></td>
                    <td class="val">– {{ $money($c->descuento) }}</td>
                </tr>
            @endif
            @if ($c->aplica_iva)
                <tr>
                    <td class="fecha"></td><td class="ruta">IVA ({{ $c->iva_porcentaje }}%)</td><td class="det"></td>
                    <td class="val">{{ $money($c->iva()) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="total"><tr>
        <td style="text-align: right; vertical-align: middle">
            <span class="lbl">VALOR TOTAL</span> &nbsp; <span class="num">{{ $money($c->total()) }}</span>
        </td>
    </tr></table>

    <table class="cards"><tr>
        <td style="width: 50%; padding-right: 8px">
            <div class="card">
                <h3>EL VALOR INCLUYE</h3>
                <ul>@foreach ($config['incluye'] as $i)<li>{{ $i }}</li>@endforeach</ul>
            </div>
        </td>
        <td style="width: 50%; padding-left: 8px">
            <div class="card">
                <h3>NUESTRO EQUIPO AUTOMOTOR</h3>
                <ul>@foreach ($config['equipo'] as $i)<li>{{ $i }}</li>@endforeach</ul>
            </div>
        </td>
    </tr></table>

    @if ($c->observaciones)
        <p class="asunto" style="margin-top: 14px"><strong>Observaciones:</strong> {{ $c->observaciones }}</p>
    @endif
</div>

</body>
</html>
