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
    @page { margin: 22px 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.6px; color: #000; }
    table { width: 100%; border-collapse: collapse; }
    td, th { border: 1px solid #000; padding: 3px 5px; vertical-align: middle; }
    .sinborde td { border: none; padding: 0; }
    .rot { font-weight: bold; font-size: 7.8px; text-transform: uppercase; }
    .gris { background: #e6e6e6; }
    .centro { text-align: center; }
    .titulo { text-align: center; font-weight: bold; font-size: 10px; padding: 6px 4px; line-height: 1.35; }
    .numero { font-family: DejaVu Sans Mono, monospace; font-size: 11.5px; letter-spacing: 1px; }
    .valor { font-size: 9px; }
    .caja { display: inline-block; border: 1px solid #000; width: 12px; height: 10px; text-align: center; line-height: 10px; font-size: 8px; }
    .esp { height: 5px; border: none; }
    .ministerio { font-weight: bold; font-size: 10px; letter-spacing: .5px; line-height: 1.3; }
    .logo { max-height: 62px; max-width: 170px; }
    .vigilado { height: 30px; }
    .pagina2 { page-break-before: always; }
    .anulado { position: fixed; top: 330px; left: 30px; font-size: 92px; color: rgba(200, 0, 0, .2); transform: rotate(-30deg); font-weight: bold; }
    .instructivo p { margin: 0 0 6px; line-height: 1.45; font-size: 9px; text-align: justify; }
    .codigos td { font-size: 8.6px; padding: 3px 6px; }
</style>
</head>
<body>
@if ($fuec->anulado())
    <div class="anulado">ANULADO</div>
@endif

{{-- ===================== ANVERSO ===================== --}}
<table>
    <tr>
        <td style="width: 50%; height: 70px" class="centro">
            <div class="ministerio">MINISTERIO DE TRANSPORTE</div>
            <div style="font-size: 7.5px; margin-bottom: 4px">República de Colombia</div>
            <img class="vigilado" src="{{ $vigilado }}" alt="Vigilado SuperTransporte">
        </td>
        <td style="width: 50%" class="centro">
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="">
            @else
                <div class="ministerio">{{ $e['razon_social'] }}</div>
            @endif
        </td>
    </tr>
    <tr>
        <td colspan="2" class="titulo">
            FORMATO ÚNICO DE EXTRACTO DEL CONTRATO DEL SERVICIO PÚBLICO DE<br>
            TRANSPORTE TERRESTRE AUTOMOTOR ESPECIAL<br>
            No. <span class="numero">{{ $n }}</span>
        </td>
    </tr>
</table>

<table style="border-top: none">
    <tr>
        <td style="width: 68%"><span class="rot">Razón social de la empresa de transporte especial:</span> <span class="valor">{{ $e['razon_social'] }}</span></td>
        <td><span class="rot">NIT:</span> <span class="valor">{{ $e['nit'] }}</span></td>
    </tr>
    <tr>
        <td colspan="2"><span class="rot">Contrato No.:</span> <span class="valor">{{ $c['numero'] }}</span></td>
    </tr>
    <tr>
        <td><span class="rot">Contratante:</span> <span class="valor">{{ $c['contratante'] }}</span></td>
        <td><span class="rot">NIT/CC:</span> <span class="valor">{{ $c['identificacion'] }}</span></td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="rot">Objeto contrato:</span>
            <span class="valor">{{ $c['objeto'] }}{{ $c['descripcion_objeto'] ? '. '.$c['descripcion_objeto'] : '' }}</span>
            @if ($c['disposicion_total'])
                <br><span class="rot">Disposición total del vehículo — zonas y municipios:</span> <span class="valor">{{ $c['zonas_disposicion'] }}</span>
            @endif
        </td>
    </tr>
    <tr>
        <td colspan="2" style="height: 30px">
            <span class="rot">Origen-destino, describiendo el recorrido:</span>
            <span class="valor">{{ $d['origen'] }} – {{ $d['destino'] }}{{ $fuec->recorrido ? '. '.$fuec->recorrido : '' }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="rot">Convenio</span> <span class="caja">{{ $d['convenio'] ? 'X' : '' }}</span>
            &nbsp;&nbsp;<span class="rot">Consorcio</span> <span class="caja"></span>
            &nbsp;&nbsp;<span class="rot">Unión temporal</span> <span class="caja"></span>
            &nbsp;&nbsp;<span class="rot">Con:</span>
            <span class="valor">{{ $d['convenio'] ? $d['convenio']['razon_social'].' — NIT '.$d['convenio']['nit'] : '' }}</span>
        </td>
    </tr>
</table>

<table style="border-top: none">
    <tr><td colspan="8" class="rot centro gris">Vigencia del contrato</td></tr>
    <tr class="centro">
        <td rowspan="2" class="rot" style="width: 19%">Fecha inicial</td>
        <td class="rot gris" style="width: 9%">Día</td><td class="rot gris" style="width: 9%">Mes</td><td class="rot gris" style="width: 13%">Año</td>
        <td rowspan="2" class="rot" style="width: 19%">Fecha vencimiento</td>
        <td class="rot gris" style="width: 9%">Día</td><td class="rot gris" style="width: 9%">Mes</td><td class="rot gris" style="width: 13%">Año</td>
    </tr>
    <tr class="centro valor">
        <td>{{ $di }}</td><td>{{ $mi }}</td><td>{{ $ai }}</td>
        <td>{{ $df }}</td><td>{{ $mf }}</td><td>{{ $af }}</td>
    </tr>
</table>

<table style="border-top: none">
    <tr><td colspan="4" class="rot centro gris">Características del vehículo</td></tr>
    <tr class="centro">
        <td class="rot gris" style="width: 25%">Placa</td><td class="rot gris" style="width: 25%">Modelo</td>
        <td class="rot gris" style="width: 25%">Marca</td><td class="rot gris" style="width: 25%">Clase</td>
    </tr>
    <tr class="centro valor">
        <td><strong>{{ $v['placa'] }}</strong></td><td>{{ $v['modelo'] }}</td>
        <td>{{ trim(($v['marca'] ?? '').' '.($v['linea'] ?? '')) }}</td><td>{{ $v['clase'] }}</td>
    </tr>
    <tr class="centro">
        <td colspan="2" class="rot gris">Número interno</td>
        <td colspan="2" class="rot gris">Número tarjeta de operación</td>
    </tr>
    <tr class="centro valor">
        <td colspan="2">{{ $v['numero_interno'] }}</td>
        <td colspan="2">{{ $v['tarjeta_operacion'] }}</td>
    </tr>
</table>

<table style="border-top: none">
    <tr class="centro">
        <td class="rot gris" style="width: 15%"></td>
        <td class="rot gris" style="width: 35%">Nombres y apellidos</td>
        <td class="rot gris" style="width: 17%">No. cédula</td>
        <td class="rot gris" style="width: 18%">No. licencia de conducción</td>
        <td class="rot gris" style="width: 15%">Vigencia</td>
    </tr>
    @foreach ([1, 2, 3] as $i)
        @php($cond = $conductores->get($i))
        <tr class="valor" style="height: 18px">
            <td class="rot">Datos del conductor {{ $i }}</td>
            <td>{{ $cond['nombre'] ?? '' }}</td>
            <td class="centro">{{ $cond['documento'] ?? '' }}</td>
            <td class="centro">{{ $cond['licencia'] ?? '' }}{{ isset($cond['categoria']) && $cond['categoria'] ? ' ('.$cond['categoria'].')' : '' }}</td>
            <td class="centro">{{ $f($cond['licencia_vence'] ?? null) }}</td>
        </tr>
    @endforeach
    <tr class="centro">
        <td class="rot gris"></td>
        <td class="rot gris">Nombres y apellidos</td>
        <td class="rot gris">No. cédula</td>
        <td class="rot gris">Teléfono</td>
        <td class="rot gris">Dirección</td>
    </tr>
    <tr class="valor" style="height: 18px">
        <td class="rot">Responsable del contratante</td>
        <td>{{ $resp['nombre'] ?? '' }}</td>
        <td class="centro">{{ $resp['documento'] ?? '' }}</td>
        <td class="centro">{{ $resp['telefono'] ?? '' }}</td>
        <td>{{ $resp['direccion'] ?? '' }}</td>
    </tr>
</table>

<table style="border-top: none">
    <tr>
        <td style="width: 46%; height: 92px; vertical-align: top">
            <div class="valor">{{ $e['direccion'] }}{{ $e['municipio'] ? ', '.$e['municipio'] : '' }}</div>
            <div class="valor">{{ collect([$e['telefono'], $e['email']])->filter()->implode(' · ') }}</div>
            <div style="margin-top: 6px; font-size: 7.5px">
                Habilitada por la Dirección Territorial {{ DireccionesTerritoriales::nombre($n ? substr($n, 0, 3) : null) }},
                Resolución {{ $e['resolucion_habilitacion'] }} de {{ $e['anio_habilitacion'] }}.
            </div>
        </td>
        <td style="width: 36%; vertical-align: bottom" class="centro">
            <div style="border-top: 1px solid #000; margin: 0 14px; padding-top: 3px">
                <strong>{{ $e['representante_legal'] }}</strong><br>
                <span class="rot">Firma y sello gerente empresa</span>
            </div>
        </td>
        <td style="width: 18%" class="centro">
            <img src="{{ $qr }}" style="width: 74px; height: 74px" alt="QR"><br>
            <span style="font-size: 6.5px">Verificación</span>
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
