{{--
    Plan de viaje / rutograma (PESV, Res. 40595/2022 paso 15). Lo arma App\Support\Pdf\PdfPlanViaje.
--}}
@php
    use App\Enums\TipoPuntoRuta;
    $s = $servicio;
    $titulo = $s ? 'PLAN DE VIAJE' : 'RUTOGRAMA';
    $horas = fn (?int $m) => $m ? \App\Support\Operacion\Jornada::horas($m) : '—';
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $titulo }}</title>
<style>
    @page { margin: 34px 40px 40px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.6px; color: #111; line-height: 1.38; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .logo { max-height: 50px; max-width: 130px; }
    .empresa { font-weight: bold; font-size: 10px; }
    .tenue { color: #555; }
    h1 { font-size: 12px; margin: 0; letter-spacing: .5px; }
    h2 { font-size: 9.6px; margin: 12px 0 4px; padding: 3px 6px; background: #1B2B3A; color: #fff; }
    .datos td { border: 1px solid #c9d1da; padding: 3px 5px; }
    .datos .et { background: #eef1f5; font-weight: bold; width: 17%; }
    .puntos th { background: #eef1f5; border: 1px solid #9aa5b1; padding: 3px 5px; text-align: left; font-size: 8px; }
    .puntos td { border: 1px solid #c9d1da; padding: 3px 5px; }
    .tipo { font-weight: bold; white-space: nowrap; }
    .critico { color: #b91c1c; } .parada { color: #15803d; } .control { color: #1d4ed8; } .emergencia { color: #7c3aed; }
    .lista { margin: 0; padding-left: 14px; }
    .lista li { margin-bottom: 2px; }
    .caja { border: 1px solid #c9d1da; padding: 5px 7px; }
    .aviso { border: 1.2px solid #b45309; color: #78350f; background: #fffbeb; padding: 5px 7px; margin-top: 8px; }
    .dos td { width: 50%; padding-right: 8px; }
    .firma-img { height: 42px; max-width: 170px; }
    .linea { border-top: 1px solid #111; padding-top: 2px; width: 60%; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 22%">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
        <td style="padding-left: 6px">
            <div class="empresa">{{ mb_strtoupper($empresa->razon_social) }}</div>
            <div class="tenue">{{ collect([$empresa->direccion, $empresa->municipio?->nombre, $empresa->telefono ? 'Tel. ' . $empresa->telefono : null])->filter()->implode(' · ') }}</div>
        </td>
        <td style="width: 30%; text-align: right">
            <h1>{{ $titulo }}</h1>
            <div class="tenue">PESV · Res. 40595 de 2022, paso 15</div>
            <div class="tenue">Generado el {{ now()->format('d/m/Y h:i a') }}</div>
        </td>
    </tr>
</table>

@if ($s)
    <h2>1. Servicio</h2>
    <table class="datos">
        <tr><td class="et">Origen → destino</td><td colspan="3"><strong>{{ $s->origen?->nombre_completo ?? $s->origen?->nombre }} → {{ $s->destino?->nombre_completo ?? $s->destino?->nombre }}</strong>@if ($s->recorrido) · {{ $s->recorrido }}@endif</td></tr>
        <tr><td class="et">Fechas y horario</td><td>{{ $s->horario()->texto() }}</td><td class="et">Estado</td><td>{{ $s->estado->getLabel() }}@if ($s->fuec) · FUEC {{ $s->fuec->numero_formateado }}@endif</td></tr>
        <tr><td class="et">Vehículo</td><td>{{ $s->vehiculo ? $s->vehiculo->placa . ' · ' . $s->vehiculo->descripcion : 'Sin asignar' }}</td><td class="et">Pasajeros</td><td>{{ $s->pasajeros ?? '—' }}</td></tr>
        <tr><td class="et">Conductores</td><td>{{ $s->conductores->map->nombre_completo->implode(', ') ?: 'Sin asignar' }}</td><td class="et">Conducción estimada</td><td>{{ $horas($s->minutosConduccion()) }} por día</td></tr>
        <tr><td class="et">Contrato</td><td colspan="3">{{ $s->contrato ? $s->contrato->numero_formateado . ' · ' . $s->contrato->cliente?->nombre : 'Sin contrato' }}</td></tr>
    </table>
    @if ($avisos)
        <div class="aviso"><strong>Avisos de jornada:</strong> {{ implode(' ', $avisos) }}</div>
    @endif
@endif

<h2>{{ $s ? '2.' : '1.' }} Rutograma{{ $ruta ? ': ' . $ruta->nombre : '' }}</h2>
@if ($ruta)
    <table class="datos">
        <tr>
            <td class="et">Ruta</td><td>{{ $ruta->origen?->nombre }} → {{ $ruta->destino?->nombre }}{{ $ruta->ida_y_vuelta ? ' (ida y vuelta)' : '' }}</td>
            <td class="et">Distancia</td><td>{{ $ruta->distancia_km ? $ruta->distancia_km . ' km' : '—' }}</td>
        </tr>
        <tr>
            <td class="et">Vías</td><td>{{ $ruta->recorrido ?: '—' }}</td>
            <td class="et">Conducción · vel. máx.</td><td>{{ \App\Models\Ruta::duracionTexto($ruta->duracion_min) }} · {{ $ruta->velocidad_maxima ? $ruta->velocidad_maxima . ' km/h' : '—' }}</td>
        </tr>
    </table>
    <table class="puntos" style="margin-top: 6px">
        <tr><th style="width: 4%">#</th><th style="width: 17%">Tipo</th><th style="width: 25%">Lugar</th><th style="width: 14%">Referencia</th><th style="width: 9%">Vel.</th><th>Indicación al conductor</th></tr>
        @forelse ($ruta->puntosDe() as $i => $p)
            @php $tipo = TipoPuntoRuta::tryFrom($p['tipo'] ?? ''); @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="tipo {{ $tipo?->value }}">{{ $tipo?->icono() }} {{ $tipo?->getLabel() }}</td>
                <td>{{ $p['nombre'] }}</td>
                <td>{{ $p['referencia'] ?? '' }}</td>
                <td>{{ ! empty($p['velocidad']) ? $p['velocidad'] . ' km/h' : '' }}</td>
                <td>{{ $p['indicacion'] ?? '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="tenue" style="text-align: center">Sin puntos registrados.</td></tr>
        @endforelse
    </table>
    <table class="dos" style="margin-top: 6px">
        <tr>
            <td><div class="caja"><strong>Rutas alternas</strong><br>{!! nl2br(e($ruta->rutas_alternas ?: '—')) !!}</div></td>
            <td><div class="caja"><strong>Contactos de emergencia en la vía</strong><br>{!! nl2br(e($ruta->contactos_emergencia ?: '—')) !!}</div></td>
        </tr>
    </table>
    @if ($ruta->recomendaciones)
        <div class="caja" style="margin-top: 6px"><strong>Recomendaciones</strong><br>{!! nl2br(e($ruta->recomendaciones)) !!}</div>
    @endif
@else
    <p class="tenue">Este servicio no tiene rutograma asignado. Identifique los puntos críticos y las paradas seguras antes de salir.</p>
@endif

<h2>{{ $s ? '3.' : '2.' }} Antes, durante y al finalizar el viaje</h2>
<table class="dos">
    <tr>
        <td>
            <strong>Requisitos para iniciar</strong>
            <ul class="lista">
                <li>Preoperacional del día hecho y APTO.</li>
                <li>Documentos a bordo y vigentes: licencia, SOAT, revisión técnico-mecánica, tarjeta de operación, pólizas y el extracto del contrato (FUEC).</li>
                <li>Descanso mínimo de {{ $empresa->jornada_descanso_horas }} h desde la jornada anterior; sin alcohol ni sustancias.</li>
                <li>Leer este plan: puntos críticos, paradas y velocidades.</li>
                <li>Botiquín, extintor y equipo de carretera completos.</li>
            </ul>
            <strong>Al finalizar</strong>
            <ul class="lista">
                <li>Registrar el kilometraje final y las novedades del viaje.</li>
                <li>Reportar cualquier incidente o casi-incidente a la empresa.</li>
            </ul>
        </td>
        <td>
            <strong>Durante el recorrido</strong>
            <ul class="lista">
                <li><strong>Fatiga:</strong> máximo {{ $empresa->jornada_max_horas_continuas }} h de conducción continua y {{ $empresa->jornada_max_horas_dia }} h al día; use las paradas seguras.</li>
                <li><strong>Velocidad:</strong> respete los límites de la vía y los de este rutograma{{ $ruta?->velocidad_maxima ? ' (máximo ' . $ruta->velocidad_maxima . ' km/h)' : '' }}.</li>
                <li><strong>Distracción:</strong> no use el celular mientras conduce; deténgase en un lugar seguro para comunicarse.</li>
                <li>Cinturón de seguridad para todos los ocupantes.</li>
                <li>Ante emergencias, use los contactos de la vía y avise a la empresa.</li>
            </ul>
        </td>
    </tr>
</table>

@if ($s)
    <h2>4. Constancia de lectura del conductor</h2>
    @if ($s->leido_at)
        @if ($firma)<img class="firma-img" src="{{ $firma }}" alt="">@endif
        <div class="linea">{{ $s->leidoPor?->nombre_completo }} · leyó el plan el {{ $s->leido_at->timezone('America/Bogota')->format('d/m/Y h:i a') }} (portal del conductor)</div>
    @else
        <div style="height: 40px"></div>
        <div class="linea">Firma del conductor · Fecha:</div>
    @endif
@endif
</body>
</html>
