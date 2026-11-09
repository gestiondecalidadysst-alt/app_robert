{{--
    FUEC según la ficha técnica del formato (anexo de la Resolución 1069 de 2015, mantenido por la
    Resolución 6652 de 2019): anverso con los datos del extracto y reverso con el instructivo del número.
--}}
@php
    use App\Support\DireccionesTerritoriales;
    use Illuminate\Support\Carbon;

    $e = $d['empresa'];
    $c = $d['contrato'];
    $v = $d['vehiculo'];
    $resp = $c['responsable'] ?? [];
    $partes = fn (Carbon $f) => [$f->format('d'), $f->format('m'), $f->format('Y')];
    [$di, $mi, $ai] = $partes($fuec->fecha_inicio);
    [$df, $mf, $af] = $partes($fuec->fecha_fin);
    $conductores = collect($d['conductores'])->keyBy('orden');
    $f = fn (?string $fecha) => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : '';
    $n = $fuec->numero;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>FUEC {{ $n }}</title>
<style>
    @page { margin: 20px 24px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.6px; color: #111; }
    table { width: 100%; border-collapse: collapse; }
    td, th { border: 1px solid #000; padding: 3px 5px; vertical-align: middle; }   /* base (reverso) */
    .rot { font-weight: bold; font-size: 7.6px; text-transform: uppercase; letter-spacing: .3px; color: #5b6b63; }
    .gris { background: #e6e6e6; }
    .centro { text-align: center; }
    .titulo { text-align: center; font-weight: bold; font-size: 10px; padding: 6px 4px; line-height: 1.35; }
    .numero { font-family: DejaVu Sans Mono, monospace; font-size: 11.5px; letter-spacing: 1px; }
    .pagina2 { page-break-before: always; }
    .anulado { position: fixed; top: 330px; left: 30px; font-size: 92px; color: rgba(200, 0, 0, .2); transform: rotate(-30deg); font-weight: bold; }
    .instructivo p { margin: 0 0 6px; line-height: 1.45; font-size: 9px; text-align: justify; }
    .codigos td { font-size: 8.6px; padding: 3px 6px; }

    /* ===== anverso (minimalista, sin rejilla; compatible con dompdf) ===== */
    .flat td, .flat th { border: none; padding: 0; }
    .cabsep { border-bottom: 1px solid #e2e6e2; }
    .vigilado { height: 30px; }
    .logo { max-height: 40px; max-width: 150px; }
    .k { font-size: 7.4px; letter-spacing: .3px; text-transform: uppercase; color: #8a948f; font-weight: bold; }
    .v { font-size: 10.5px; color: #111; }
    .min { font-weight: bold; font-size: 11px; color: #15312a; }
    .minsub { font-size: 8px; color: #8a948f; }
    .marca { font-weight: bold; font-size: 15px; color: #111; }
    .ttl { font-weight: bold; font-size: 9px; color: #111; line-height: 1.35; }
    .exlb { font-size: 7px; letter-spacing: 2px; color: #8a948f; text-transform: uppercase; }
    .sl { font-size: 8.5px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; color: #111; padding: 13px 0 7px; }
    .caja { display: inline-block; border: 1px solid #9aa; width: 12px; height: 11px; text-align: center; line-height: 11px; font-size: 8px; vertical-align: middle; }
    .vigd { font-size: 14px; font-weight: bold; color: #111; letter-spacing: .5px; }
    .lite td, .lite th { border: none; }
    .lite th { padding: 3px 8px 5px 0; text-align: left; font-size: 7.4px; letter-spacing: .3px; text-transform: uppercase; color: #8a948f; font-weight: bold; }
    .lite td { padding: 7px 8px 7px 0; font-size: 10px; }
    .lite td.rolk { font-size: 7.6px; color: #8a948f; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
    .lite .rt td { border-top: 1px solid #ededed; }
    .placa { font-weight: bold; letter-spacing: 1px; }
    .firma-line { border-top: 1px solid #333; margin: 2px 12px 0; padding-top: 3px; text-align: center; }
</style>
</head>
<body>
@if ($fuec->anulado())
    <div class="anulado">ANULADO</div>
@endif

{{-- ===================== ANVERSO ===================== --}}
<table class="flat cabsep">
    <tr>
        <td style="width: 56%; padding: 2px 0 12px">
            <table class="flat" style="width: auto"><tr>
                <td style="vertical-align: middle; padding-right: 11px">
                    <img class="vigilado" src="{{ $vigilado }}" alt="Vigilado SuperTransporte">
                </td>
                <td style="vertical-align: middle">
                    <div class="min">MINISTERIO DE TRANSPORTE</div>
                    <div class="minsub">República de Colombia</div>
                </td>
            </tr></table>
        </td>
        <td style="width: 44%; padding: 2px 0 12px; text-align: right; vertical-align: middle">
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt=""><br>
            @else
                <span class="marca">{{ $e['razon_social'] }}</span><br>
            @endif
            <span class="minsub">NIT {{ $e['nit'] }}</span>
        </td>
    </tr>
</table>

<table class="flat" style="margin-top: 14px">
    <tr>
        <td style="width: 72%; vertical-align: top">
            <div class="ttl">FORMATO ÚNICO DE EXTRACTO DEL CONTRATO DEL SERVICIO PÚBLICO DE TRANSPORTE TERRESTRE AUTOMOTOR ESPECIAL</div>
        </td>
        <td style="width: 28%; text-align: right; vertical-align: top">
            <div class="exlb">Extracto No.</div>
            <div class="numero" style="font-size: 12px">{{ $n }}</div>
        </td>
    </tr>
</table>

<div class="sl">Partes del contrato</div>
<table class="flat">
    <tr>
        <td style="width: 50%; padding-bottom: 9px; vertical-align: top"><div class="k">Razón social empresa de transporte</div><div class="v">{{ $e['razon_social'] }}</div></td>
        <td style="width: 24%; padding-bottom: 9px; vertical-align: top"><div class="k">NIT</div><div class="v">{{ $e['nit'] }}</div></td>
        <td style="width: 26%; padding-bottom: 9px; vertical-align: top"><div class="k">Contrato No.</div><div class="v">{{ $c['numero'] }}</div></td>
    </tr>
    <tr>
        <td style="padding-bottom: 9px; vertical-align: top"><div class="k">Contratante</div><div class="v">{{ $c['contratante'] }}</div></td>
        <td colspan="2" style="padding-bottom: 9px; vertical-align: top"><div class="k">NIT / CC</div><div class="v">{{ $c['identificacion'] }}</div></td>
    </tr>
    <tr>
        <td colspan="3" style="padding-bottom: 9px"><div class="k">Objeto del contrato</div><div class="v">{{ $c['objeto'] }}{{ $c['descripcion_objeto'] ? '. '.$c['descripcion_objeto'] : '' }}@if ($c['disposicion_total']) — Disposición total del vehículo (zonas/municipios): {{ $c['zonas_disposicion'] }}@endif</div></td>
    </tr>
    <tr>
        <td colspan="3" style="padding-bottom: 9px"><div class="k">Origen – destino, describiendo el recorrido</div><div class="v">{{ $d['origen'] }} – {{ $d['destino'] }}{{ $fuec->recorrido ? '. '.$fuec->recorrido : '' }}</div></td>
    </tr>
    <tr>
        <td colspan="3">
            <span class="caja">{{ $d['convenio'] ? 'X' : '' }}</span> <span class="k">Convenio</span>
            &nbsp;&nbsp;<span class="caja"></span> <span class="k">Consorcio</span>
            &nbsp;&nbsp;<span class="caja"></span> <span class="k">Unión temporal</span>
            &nbsp;&nbsp;<span class="k">Con:</span>
            <span class="v">{{ $d['convenio'] ? $d['convenio']['razon_social'].' — NIT '.$d['convenio']['nit'] : '—' }}</span>
        </td>
    </tr>
</table>

<div class="sl">Vigencia del contrato</div>
<table class="flat" style="width: auto">
    <tr>
        <td style="padding-right: 48px; vertical-align: top">
            <div class="k">Fecha inicial</div>
            <div class="vigd">{{ $di }} / {{ $mi }} / {{ $ai }}</div>
            <div style="font-size: 7.3px; color: #8a948f">Día · Mes · Año</div>
        </td>
        <td style="vertical-align: top">
            <div class="k">Fecha de vencimiento</div>
            <div class="vigd">{{ $df }} / {{ $mf }} / {{ $af }}</div>
            <div style="font-size: 7.3px; color: #8a948f">Día · Mes · Año</div>
        </td>
    </tr>
</table>

<div class="sl">Características del vehículo</div>
<table class="lite">
    <tr>
        <th>Placa</th><th>Modelo</th><th>Marca</th><th>Clase</th><th>Número interno</th><th>Tarjeta de operación</th>
    </tr>
    <tr class="rt">
        <td class="placa">{{ $v['placa'] }}</td><td>{{ $v['modelo'] }}</td>
        <td>{{ trim(($v['marca'] ?? '').' '.($v['linea'] ?? '')) }}</td><td>{{ $v['clase'] }}</td>
        <td>{{ $v['numero_interno'] }}</td><td>{{ $v['tarjeta_operacion'] }}</td>
    </tr>
</table>

<div class="sl">Conductores autorizados y responsable</div>
<table class="lite">
    <tr>
        <th style="width: 17%"></th><th>Nombres y apellidos</th>
        <th>No. cédula</th><th>Licencia (cat.)</th><th>Vigencia</th>
    </tr>
    @foreach ([1, 2, 3] as $i)
        @php($cond = $conductores->get($i))
        <tr class="rt">
            <td class="rolk">Conductor {{ $i }}</td>
            <td>{{ $cond['nombre'] ?? '' }}</td>
            <td>{{ $cond['documento'] ?? '' }}</td>
            <td>{{ $cond['licencia'] ?? '' }}{{ isset($cond['categoria']) && $cond['categoria'] ? ' ('.$cond['categoria'].')' : '' }}</td>
            <td>{{ $f($cond['licencia_vence'] ?? null) }}</td>
        </tr>
    @endforeach
    <tr class="rt">
        <td class="rolk">Responsable del contratante</td>
        <td>{{ $resp['nombre'] ?? '' }}</td>
        <td>{{ $resp['documento'] ?? '' }}</td>
        <td>{{ ! empty($resp['telefono']) ? 'Tel '.$resp['telefono'] : '' }}</td>
        <td>{{ $resp['direccion'] ?? '' }}</td>
    </tr>
</table>

<table class="flat" style="margin-top: 20px">
    <tr>
        <td style="width: 46%; vertical-align: bottom">
            <div class="v">{{ $e['direccion'] }}{{ $e['municipio'] ? ', '.$e['municipio'] : '' }}</div>
            <div class="v">{{ collect([$e['telefono'], $e['email']])->filter()->implode(' · ') }}</div>
            <div style="margin-top: 5px; font-size: 7.5px; color: #8a948f">
                Habilitada por la Dirección Territorial {{ DireccionesTerritoriales::nombre($n ? substr($n, 0, 3) : null) }},
                Resolución {{ $e['resolucion_habilitacion'] }} de {{ $e['anio_habilitacion'] }}.
            </div>
        </td>
        <td style="width: 36%; text-align: center; vertical-align: bottom">
            @if ($firma)
                <img src="{{ $firma }}" style="max-height: 46px; max-width: 165px" alt=""><br>
            @endif
            <div class="firma-line">
                <strong>{{ $e['representante_legal'] }}</strong><br>
                <span class="k">Firma y sello gerente empresa</span>
            </div>
        </td>
        <td style="width: 18%; text-align: center; vertical-align: bottom">
            <img src="{{ $qr }}" style="width: 70px; height: 70px" alt="QR"><br>
            <span style="font-size: 6.5px; color: #8a948f">Verificación</span>
        </td>
    </tr>
</table>

@if ($fuec->anulado())
    <div style="margin-top: 6px; color: #b00; font-weight: bold">
        ANULADO el {{ $fuec->anulado_at->timezone('America/Bogota')->format('d/m/Y') }}: {{ $fuec->motivo_anulacion }}
    </div>
@endif

{{-- ===================== REVERSO ===================== --}}
<div class="pagina2 instructivo">
    <table>
        <tr><td class="titulo">INSTRUCTIVO PARA LA DETERMINACIÓN DEL NÚMERO CONSECUTIVO DEL FUEC</td></tr>
    </table>
    <div style="padding: 10px 2px 0">
        <p>El Formato Único de Extracto del Contrato "FUEC" estará constituido por los siguientes números:</p>
        <p><strong>a)</strong> Los tres primeros dígitos de izquierda a derecha corresponderán al código de la Dirección Territorial que otorgó la habilitación o de aquella a la cual se hubiese trasladado la empresa de Servicio Público de Transporte Terrestre Automotor Especial.</p>
        <p><strong>b)</strong> Los cuatro dígitos siguientes señalarán el número de resolución mediante la cual se otorgó la habilitación de la empresa. En caso que la resolución no tenga estos dígitos, los faltantes serán completados con ceros a la izquierda.</p>
        <p><strong>c)</strong> Los dos siguientes dígitos corresponderán a los dos últimos del año en que la empresa fue habilitada.</p>
        <p><strong>d)</strong> A continuación, cuatro dígitos que corresponderán al año en que se expide el extracto del contrato.</p>
        <p><strong>e)</strong> Posteriormente, cuatro dígitos que identifican el número del contrato. La numeración debe ser consecutiva, establecida por cada empresa y continuará con la numeración dada a los contratos de prestación de servicios celebrados para el transporte de estudiantes, asalariados, turistas, usuarios del servicio de salud y grupos específicos de usuarios. En caso de que el número de contrato exceda los cuatro dígitos, se tomarán los cuatro últimos.</p>
        <p><strong>f)</strong> Finalmente, los cuatro últimos dígitos corresponderán al número consecutivo del extracto de contrato que se expida para la ejecución de cada contrato. Se debe expedir un nuevo extracto por vencimiento del plazo inicial del mismo o por cambio del vehículo. En caso de que el número consecutivo exceda los cuatro dígitos, se tomarán los cuatro últimos.</p>

        <p style="margin-top: 10px"><strong>Códigos de las Direcciones Territoriales</strong></p>
        @php($codigos = collect(DireccionesTerritoriales::CODIGOS)->sortKeys()->chunk(10))
        <table class="codigos">
            <tr class="gris">
                <td class="rot">Dirección Territorial</td><td class="rot centro">Código</td>
                <td class="rot">Dirección Territorial</td><td class="rot centro">Código</td>
            </tr>
            @foreach (range(0, 9) as $fila)
                @php($izq = $codigos[0]->slice($fila, 1))
                @php($der = ($codigos[1] ?? collect())->slice($fila, 1))
                <tr>
                    <td>{{ $izq->first() }}</td><td class="centro">{{ $izq->keys()->first() }}</td>
                    <td>{{ $der->first() }}</td><td class="centro">{{ $der->keys()->first() }}</td>
                </tr>
            @endforeach
        </table>

        <p style="margin-top: 10px"><strong>Ejemplo:</strong> empresa habilitada por la Dirección Territorial Cundinamarca en el año 2012, con resolución de habilitación No. 0155, que expide el primer extracto del contrato en el año 2015, del contrato 255: <strong>425 0155 12 2015 0255 0001</strong>.</p>

        <p style="margin-top: 14px"><strong>Este extracto:</strong>
            <span class="numero">{{ substr($n, 0, 3) }} {{ substr($n, 3, 4) }} {{ substr($n, 7, 2) }} {{ substr($n, 9, 4) }} {{ substr($n, 13, 4) }} {{ substr($n, 17, 4) }}</span>
            — expedido el {{ $fuec->created_at?->timezone('America/Bogota')->format('d/m/Y h:i a') }}.
            El conductor debe portar el original durante todo el recorrido; la copia queda en los archivos de la empresa (art. 12, Res. 6652 de 2019).
            No es válido con tachones o enmendaduras.
        </p>
    </div>
</div>
</body>
</html>
