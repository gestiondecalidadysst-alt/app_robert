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
    .valor { font-size: 10px; color: #111; }
    .caja { display: inline-block; border: 1px solid #333; width: 12px; height: 11px; text-align: center; line-height: 11px; font-size: 8px; vertical-align: middle; }
    .vigilado { height: 26px; }
    .logo { max-height: 42px; max-width: 150px; }
    .pagina2 { page-break-before: always; }
    .anulado { position: fixed; top: 330px; left: 30px; font-size: 92px; color: rgba(200, 0, 0, .2); transform: rotate(-30deg); font-weight: bold; }
    .instructivo p { margin: 0 0 6px; line-height: 1.45; font-size: 9px; text-align: justify; }
    .codigos td { font-size: 8.6px; padding: 3px 6px; }

    /* ===== anverso (diseño de tarjetas, compatible con dompdf) ===== */
    .cab td { border: none; padding: 0; }
    .dk { background: #15312a; }
    .min { color: #fff; font-weight: bold; font-size: 11px; letter-spacing: .3px; }
    .minsub { color: #aec6ba; font-size: 8px; }
    .marca { color: #fff; font-weight: bold; font-size: 15px; }
    .marcasub { color: #aec6ba; font-size: 8px; }
    .plate td { border: none; background: #fff; }
    .sub td { border: none; padding: 8px 2px 9px; }
    .sub { border-bottom: 3px solid #74a92c; }
    .ttl { font-weight: bold; font-size: 9px; color: #15312a; line-height: 1.35; }
    .badge { border: 1.5px solid #74a92c; padding: 4px 8px; text-align: center; }
    .badge .lb { font-size: 6.5px; letter-spacing: 1.5px; color: #5b6b63; text-transform: uppercase; }
    .card { margin-top: 8px; }
    .card td, .card th { border: 1px solid #d9ded9; }
    .hd td { background: #eef2ee; color: #15312a; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; font-size: 8px; padding: 4px 7px; }
    .cell .rot { display: block; margin-bottom: 1px; }
    .ct th { background: #f6f8f6; color: #5b6b63; font-size: 7.5px; text-transform: uppercase; letter-spacing: .3px; font-weight: bold; }
    .placa { font-weight: bold; letter-spacing: 1px; }
    .pie td { border: none; }
    .firma { border-top: 1px solid #000; margin: 26px 10px 3px; padding-top: 3px; text-align: center; }
    .qr { border: 1px solid #d9ded9; }
</style>
</head>
<body>
@if ($fuec->anulado())
    <div class="anulado">ANULADO</div>
@endif

{{-- ===================== ANVERSO ===================== --}}
<table class="cab">
    <tr>
        <td class="dk" style="width: 56%; padding: 11px 14px">
            <table style="width: auto"><tr>
                <td class="plate" style="padding: 4px 7px; vertical-align: middle">
                    <img class="vigilado" src="{{ $vigilado }}" alt="Vigilado SuperTransporte">
                </td>
                <td style="border: none; padding-left: 11px; vertical-align: middle">
                    <div class="min">MINISTERIO DE TRANSPORTE</div>
                    <div class="minsub">República de Colombia</div>
                </td>
            </tr></table>
        </td>
        <td class="dk" style="width: 44%; padding: 11px 14px; text-align: right; vertical-align: middle">
            @if ($logo)
                <table class="plate" style="width: auto; float: right"><tr>
                    <td style="padding: 4px 8px; text-align: center"><img class="logo" src="{{ $logo }}" alt=""></td>
                </tr></table>
                <div style="clear: both"></div>
            @else
                <div class="marca">{{ $e['razon_social'] }}</div>
            @endif
            <div class="marcasub" style="margin-top: 4px">NIT {{ $e['nit'] }}</div>
        </td>
    </tr>
</table>

<table class="sub">
    <tr>
        <td style="width: 71%">
            <div class="ttl">FORMATO ÚNICO DE EXTRACTO DEL CONTRATO DEL SERVICIO PÚBLICO DE TRANSPORTE TERRESTRE AUTOMOTOR ESPECIAL</div>
        </td>
        <td style="width: 29%; text-align: right">
            <div class="badge">
                <span class="lb">Extracto No.</span><br>
                <span class="numero">{{ $n }}</span>
            </div>
        </td>
    </tr>
</table>

<table class="card">
    <tr class="hd"><td colspan="6">Partes del contrato</td></tr>
    <tr>
        <td colspan="4" class="cell"><span class="rot">Razón social empresa de transporte</span><span class="valor">{{ $e['razon_social'] }}</span></td>
        <td colspan="2" class="cell"><span class="rot">NIT</span><span class="valor">{{ $e['nit'] }}</span></td>
    </tr>
    <tr>
        <td colspan="1" class="cell"><span class="rot">Contrato No.</span><span class="valor">{{ $c['numero'] }}</span></td>
        <td colspan="3" class="cell"><span class="rot">Contratante</span><span class="valor">{{ $c['contratante'] }}</span></td>
        <td colspan="2" class="cell"><span class="rot">NIT / CC</span><span class="valor">{{ $c['identificacion'] }}</span></td>
    </tr>
    <tr>
        <td colspan="6" class="cell">
            <span class="rot">Objeto del contrato</span>
            <span class="valor">{{ $c['objeto'] }}{{ $c['descripcion_objeto'] ? '. '.$c['descripcion_objeto'] : '' }}@if ($c['disposicion_total']) — Disposición total del vehículo (zonas/municipios): {{ $c['zonas_disposicion'] }}@endif</span>
        </td>
    </tr>
    <tr>
        <td colspan="6" class="cell">
            <span class="rot">Origen – destino, describiendo el recorrido</span>
            <span class="valor">{{ $d['origen'] }} – {{ $d['destino'] }}{{ $fuec->recorrido ? '. '.$fuec->recorrido : '' }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="6">
            <span class="caja">{{ $d['convenio'] ? 'X' : '' }}</span> <span class="rot" style="display: inline">Convenio</span>
            &nbsp;&nbsp;<span class="caja"></span> <span class="rot" style="display: inline">Consorcio</span>
            &nbsp;&nbsp;<span class="caja"></span> <span class="rot" style="display: inline">Unión temporal</span>
            &nbsp;&nbsp;<span class="rot" style="display: inline">Con:</span>
            <span class="valor">{{ $d['convenio'] ? $d['convenio']['razon_social'].' — NIT '.$d['convenio']['nit'] : '—' }}</span>
        </td>
    </tr>
</table>

<table class="card ct">
    <tr class="hd"><td colspan="8">Vigencia del contrato</td></tr>
    <tr class="centro">
        <th rowspan="2" style="width: 18%">Fecha inicial</th>
        <th style="width: 9%">Día</th><th style="width: 9%">Mes</th><th style="width: 14%">Año</th>
        <th rowspan="2" style="width: 18%">Fecha vencimiento</th>
        <th style="width: 9%">Día</th><th style="width: 9%">Mes</th><th style="width: 14%">Año</th>
    </tr>
    <tr class="centro valor">
        <td>{{ $di }}</td><td>{{ $mi }}</td><td>{{ $ai }}</td>
        <td>{{ $df }}</td><td>{{ $mf }}</td><td>{{ $af }}</td>
    </tr>
</table>

<table class="card ct">
    <tr class="hd"><td colspan="6">Características del vehículo</td></tr>
    <tr class="centro">
        <th>Placa</th><th>Modelo</th><th>Marca</th><th>Clase</th><th>Número interno</th><th>Tarjeta de operación</th>
    </tr>
    <tr class="centro valor">
        <td class="placa">{{ $v['placa'] }}</td><td>{{ $v['modelo'] }}</td>
        <td>{{ trim(($v['marca'] ?? '').' '.($v['linea'] ?? '')) }}</td><td>{{ $v['clase'] }}</td>
        <td>{{ $v['numero_interno'] }}</td><td>{{ $v['tarjeta_operacion'] }}</td>
    </tr>
</table>

<table class="card ct">
    <tr class="hd"><td colspan="5">Conductores autorizados y responsable</td></tr>
    <tr>
        <th style="width: 17%"></th><th style="text-align: left">Nombres y apellidos</th>
        <th>No. cédula</th><th>Licencia (cat.)</th><th>Vigencia</th>
    </tr>
    @foreach ([1, 2, 3] as $i)
        @php($cond = $conductores->get($i))
        <tr class="valor" style="height: 16px">
            <td class="rot">Conductor {{ $i }}</td>
            <td>{{ $cond['nombre'] ?? '' }}</td>
            <td class="centro">{{ $cond['documento'] ?? '' }}</td>
            <td class="centro">{{ $cond['licencia'] ?? '' }}{{ isset($cond['categoria']) && $cond['categoria'] ? ' ('.$cond['categoria'].')' : '' }}</td>
            <td class="centro">{{ $f($cond['licencia_vence'] ?? null) }}</td>
        </tr>
    @endforeach
    <tr>
        <th></th><th style="text-align: left">Nombres y apellidos</th>
        <th>No. cédula</th><th>Teléfono</th><th>Dirección</th>
    </tr>
    <tr class="valor" style="height: 16px">
        <td class="rot">Responsable del contratante</td>
        <td>{{ $resp['nombre'] ?? '' }}</td>
        <td class="centro">{{ $resp['documento'] ?? '' }}</td>
        <td class="centro">{{ $resp['telefono'] ?? '' }}</td>
        <td>{{ $resp['direccion'] ?? '' }}</td>
    </tr>
</table>

<table class="pie" style="margin-top: 10px">
    <tr>
        <td style="width: 46%; vertical-align: bottom">
            <div class="valor">{{ $e['direccion'] }}{{ $e['municipio'] ? ', '.$e['municipio'] : '' }}</div>
            <div class="valor">{{ collect([$e['telefono'], $e['email']])->filter()->implode(' · ') }}</div>
            <div style="margin-top: 5px; font-size: 7.5px; color: #5b6b63">
                Habilitada por la Dirección Territorial {{ DireccionesTerritoriales::nombre($n ? substr($n, 0, 3) : null) }},
                Resolución {{ $e['resolucion_habilitacion'] }} de {{ $e['anio_habilitacion'] }}.
            </div>
        </td>
        <td style="width: 36%; vertical-align: bottom">
            <div class="firma">
                <strong>{{ $e['representante_legal'] }}</strong><br>
                <span class="rot">Firma y sello gerente empresa</span>
            </div>
        </td>
        <td style="width: 18%; text-align: center; vertical-align: bottom">
            <img src="{{ $qr }}" class="qr" style="width: 72px; height: 72px" alt="QR"><br>
            <span style="font-size: 6.5px; color: #5b6b63">Verificación</span>
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
