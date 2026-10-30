@php
    $fecha = fn (?string $f) => $f ? \Illuminate\Support\Carbon::parse($f)->format('d/m/Y') : '—';
    $e = $d['empresa'];
    $c = $d['contrato'];
    $v = $d['vehiculo'];
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>FUEC {{ $fuec->numero }}</title>
<style>
    @page { margin: 28px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111; }
    table { width: 100%; border-collapse: collapse; }
    .encabezado td { vertical-align: middle; }
    .logo { max-height: 58px; max-width: 150px; }
    .empresa { font-size: 12px; font-weight: bold; }
    .titulo { text-align: center; font-weight: bold; font-size: 10.5px; margin: 10px 0 4px; line-height: 1.35; }
    .numero { text-align: center; font-size: 13px; font-weight: bold; letter-spacing: 1px; margin-bottom: 10px; }
    .numero span { font-family: DejaVu Sans Mono, monospace; }
    .seccion { background: #1B2B3A; color: #fff; font-weight: bold; padding: 4px 6px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .4px; }
    .datos td { border: 1px solid #9aa5b1; padding: 4px 6px; vertical-align: top; }
    .etq { display: block; font-size: 7.5px; color: #555; text-transform: uppercase; margin-bottom: 1px; }
    .placa { font-family: DejaVu Sans Mono, monospace; font-weight: bold; font-size: 12px; background: #FFD23F; border: 1.5px solid #111; padding: 0 5px; }
    .grilla th { background: #eef1f5; border: 1px solid #9aa5b1; padding: 4px 6px; font-size: 8px; text-align: left; text-transform: uppercase; }
    .grilla td { border: 1px solid #9aa5b1; padding: 4px 6px; }
    .espacio { height: 8px; }
    .firma { margin-top: 34px; }
    .firma td { vertical-align: bottom; }
    .linea { border-top: 1px solid #111; padding-top: 3px; width: 260px; }
    .pie { font-size: 7.5px; color: #555; margin-top: 10px; line-height: 1.4; }
    .anulado { position: fixed; top: 300px; left: 40px; font-size: 90px; color: rgba(200, 0, 0, .18); transform: rotate(-30deg); font-weight: bold; }
</style>
</head>
<body>
@if ($fuec->anulado())
    <div class="anulado">ANULADO</div>
@endif

<table class="encabezado">
    <tr>
        @if ($logo)
        <td style="width: 160px"><img class="logo" src="{{ $logo }}" alt=""></td>
        @endif
        <td>
            <div class="empresa">{{ $e['razon_social'] }}</div>
            <div>NIT {{ $e['nit'] }}</div>
            <div>{{ $e['direccion'] }}{{ $e['municipio'] ? ' · '.$e['municipio'] : '' }}</div>
            <div>{{ collect([$e['telefono'], $e['email']])->filter()->implode(' · ') }}</div>
            <div>Habilitación: Resolución {{ $e['resolucion_habilitacion'] }} de {{ $e['anio_habilitacion'] }}</div>
        </td>
        <td style="width: 110px; text-align: right">
            <img src="{{ $qr }}" style="width: 96px; height: 96px" alt="QR">
        </td>
    </tr>
</table>

<div class="titulo">
    FORMATO ÚNICO DE EXTRACTO DEL CONTRATO DEL SERVICIO PÚBLICO DE<br>
    TRANSPORTE TERRESTRE AUTOMOTOR ESPECIAL
</div>
<div class="numero">FUEC No. <span>{{ $fuec->numero }}</span></div>

<div class="seccion">Contrato</div>
<table class="datos">
    <tr>
        <td style="width: 18%"><span class="etq">Contrato No.</span>{{ $c['numero'] }}</td>
        <td style="width: 52%"><span class="etq">Contratante</span>{{ $c['contratante'] }}</td>
        <td style="width: 30%"><span class="etq">NIT / CC</span>{{ $c['identificacion'] }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="etq">Objeto del contrato</span>{{ $c['objeto'] }}{{ $c['descripcion_objeto'] ? ' — '.$c['descripcion_objeto'] : '' }}</td>
    </tr>
    <tr>
        <td colspan="2"><span class="etq">Origen – destino</span>{{ $d['origen'] }} – {{ $d['destino'] }}</td>
        <td><span class="etq">Convenio de colaboración empresarial</span>{{ $d['convenio'] ? $d['convenio']['razon_social'].' (NIT '.$d['convenio']['nit'].')' : 'No aplica' }}</td>
    </tr>
    @if ($fuec->recorrido)
    <tr>
        <td colspan="3"><span class="etq">Recorrido</span>{{ $fuec->recorrido }}</td>
    </tr>
    @endif
    @if ($c['disposicion_total'])
    <tr>
        <td colspan="3"><span class="etq">Disposición total del vehículo — zonas y municipios</span>{{ $c['zonas_disposicion'] ?: 'Según contrato' }}</td>
    </tr>
    @endif
</table>

<div class="espacio"></div>
<div class="seccion">Vigencia</div>
<table class="datos">
    <tr>
        <td style="width: 50%"><span class="etq">Fecha inicial</span>{{ $fecha($fuec->fecha_inicio->toDateString()) }}</td>
        <td style="width: 50%"><span class="etq">Fecha de vencimiento</span>{{ $fecha($fuec->fecha_fin->toDateString()) }}</td>
    </tr>
</table>

<div class="espacio"></div>
<div class="seccion">Características del vehículo</div>
<table class="grilla">
    <tr>
        <th>Placa</th><th>Modelo</th><th>Marca</th><th>Clase</th><th>Número interno</th><th>Tarjeta de operación</th>
    </tr>
    <tr>
        <td><span class="placa">{{ $v['placa'] }}</span></td>
        <td>{{ $v['modelo'] }}</td>
        <td>{{ trim(($v['marca'] ?? '').' '.($v['linea'] ?? '')) }}</td>
        <td>{{ $v['clase'] }}</td>
        <td>{{ $v['numero_interno'] ?: '—' }}</td>
        <td>{{ $v['tarjeta_operacion'] ?: '—' }}</td>
    </tr>
</table>

<div class="espacio"></div>
<div class="seccion">Conductores</div>
<table class="grilla">
    <tr>
        <th style="width: 6%">No.</th><th>Nombre</th><th>Identificación</th><th>Licencia</th><th>Categoría</th><th>Vigencia licencia</th>
    </tr>
    @foreach ($d['conductores'] as $cond)
    <tr>
        <td>{{ $cond['orden'] }}</td>
        <td>{{ $cond['nombre'] }}</td>
        <td>{{ $cond['tipo_documento'] }} {{ $cond['documento'] }}</td>
        <td>{{ $cond['licencia'] ?: '—' }}</td>
        <td>{{ $cond['categoria'] ?: '—' }}</td>
        <td>{{ $fecha($cond['licencia_vence']) }}</td>
    </tr>
    @endforeach
</table>

@if (! empty($c['responsable']))
<div class="espacio"></div>
<div class="seccion">Responsable del contrato</div>
<table class="datos">
    <tr>
        <td style="width: 40%"><span class="etq">Nombre</span>{{ $c['responsable']['nombre'] ?? '—' }}</td>
        <td style="width: 20%"><span class="etq">Documento</span>{{ $c['responsable']['documento'] ?? '—' }}</td>
        <td style="width: 20%"><span class="etq">Teléfono</span>{{ $c['responsable']['telefono'] ?? '—' }}</td>
        <td style="width: 20%"><span class="etq">Dirección</span>{{ $c['responsable']['direccion'] ?? '—' }}</td>
    </tr>
</table>
@endif

<table class="firma">
    <tr>
        <td>
            <div class="linea">
                <strong>{{ $e['representante_legal'] ?: 'Representante legal' }}</strong><br>
                {{ $e['representante_documento'] ? 'C.C. '.$e['representante_documento'].' · ' : '' }}Gerente / Representante legal
            </div>
        </td>
        <td style="text-align: right; font-size: 8px; color: #555">
            Expedido el {{ $fuec->created_at?->timezone('America/Bogota')->format('d/m/Y h:i a') }}<br>
            Verifique este documento escaneando el código QR.
        </td>
    </tr>
</table>

<div class="pie">
    Documento expedido conforme a la Resolución 6652 de 2019 del Ministerio de Transporte. El conductor debe portar el original
    durante todo el recorrido. No es válido con tachones o enmendaduras. Su vigencia no puede superar la del contrato.
    @if ($fuec->anulado())
        <br><strong style="color: #b00">ANULADO el {{ $fuec->anulado_at->timezone('America/Bogota')->format('d/m/Y') }}: {{ $fuec->motivo_anulacion }}</strong>
    @endif
</div>
</body>
</html>
