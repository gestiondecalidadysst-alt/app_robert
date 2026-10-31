{{--
    Contrato de prestación de servicios. Encabezado y pie fijos: los márgenes de @page reservan
    su espacio para que nunca se monten sobre el texto. La numeración "Página X / Y" la escribe
    App\Support\Pdf\PdfContrato sobre cada página.
--}}
@php
    $e = $empresa;
    $v = $valores;
    $doc = $documento;
    $nombreEmpresa = mb_strtoupper($e['razon_social'] ?? '');
    $comercial = $e['nombre_comercial'] ?? null;
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Contrato {{ $v['contrato.numero'] ?? '' }}</title>
<style>
    @page { margin: 120px 54px 104px 54px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.4px; color: #111; line-height: 1.42; }
    header { position: fixed; top: -100px; left: 0; right: 0; height: 88px; }
    footer { position: fixed; bottom: -86px; left: 0; right: 0; height: 74px; text-align: center; font-size: 7.6px; color: #333; }
    table { width: 100%; border-collapse: collapse; }
    .enc td { vertical-align: middle; }
    .enc-empresa { text-align: center; font-weight: bold; font-size: 10.5px; line-height: 1.3; }
    .enc-formato { text-align: center; font-weight: bold; font-size: 8.4px; margin-top: 6px; }
    .enc-codigo { font-size: 8.2px; font-weight: bold; text-align: left; line-height: 1.5; }
    .logo { max-height: 70px; max-width: 150px; }
    .pie-img { max-height: 34px; max-width: 520px; margin-top: 3px; }
    .contenido h2 { text-align: center; font-size: 11px; margin: 4px 0 12px; }
    .contenido h3 { font-size: 10px; margin: 10px 0 6px; }
    .contenido p { text-align: justify; margin: 0 0 8px; }
    .contenido ul, .contenido ol { margin: 0 0 8px 18px; padding: 0; }
    .firmas { margin-top: 26px; page-break-inside: avoid; }
    .firmas td { width: 50%; vertical-align: top; padding: 0 18px; font-size: 8.8px; }
    .firma-img { height: 58px; max-width: 220px; }
    .firma-hueco { height: 58px; }
    .linea { border-top: 1px solid #111; padding-top: 3px; }
    .marca { position: fixed; top: 300px; left: 10px; font-size: 92px; color: rgba(200, 0, 0, .15); transform: rotate(-30deg); font-weight: bold; }
    .certificado { page-break-before: always; }
    .certificado h2 { text-align: center; font-size: 12px; margin: 0 0 4px; }
    .certificado .sub { text-align: center; font-size: 8px; color: #444; margin-bottom: 10px; }
    .ev td, .ev th { border: 1px solid #9aa5b1; padding: 4px 6px; font-size: 8.4px; vertical-align: top; text-align: left; }
    .ev th { background: #eef1f5; width: 31%; }
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 7.8px; word-break: break-all; }
    .legal { font-size: 7.8px; color: #333; text-align: justify; margin-top: 10px; }
</style>
</head>
<body>
@if ($marca)
    <div class="marca">{{ $marca }}</div>
@endif

<header>
    <table class="enc">
        <tr>
            <td style="width: 26%">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
            <td style="width: 50%">
                <div class="enc-empresa">
                    {{ $nombreEmpresa }}
                    @if ($comercial && mb_strtoupper($comercial) !== $nombreEmpresa)<br>-{{ mb_strtoupper($comercial) }}-@endif
                </div>
                <div class="enc-formato">{{ mb_strtoupper($e['titulo_formato'] ?? 'FORMATO DE CONTRATO DE PRESTACIÓN DE SERVICIO') }}</div>
            </td>
            <td style="width: 24%" class="enc-codigo">
                @if (! empty($e['codigo_formato']))Código: {{ $e['codigo_formato'] }}<br>@endif
                Versión: {{ $e['version_formato'] ?? '01' }}
            </td>
        </tr>
    </table>
</header>

<footer>
    <div><strong>{{ mb_strtoupper(trim(($e['direccion'] ?? '').(! empty($e['ciudad']) ? ' - '.$e['ciudad'] : ''))) }}</strong></div>
    <div>{{ collect([! empty($e['telefono']) ? 'Teléfono: '.$e['telefono'] : null, ! empty($e['email']) ? 'Correo: '.$e['email'] : null])->filter()->implode(' · ') }}</div>
    @if ($pie)<img class="pie-img" src="{{ $pie }}" alt="">@endif
</footer>

<main class="contenido">
    {!! $contenido !!}

    <table class="firmas">
        <tr>
            <td>
                @if ($firmaContratista)
                    <img class="firma-img" src="{{ $firmaContratista }}" alt="">
                @else
                    <div class="firma-hueco"></div>
                @endif
                <div class="linea">
                    <strong>{{ $e['representante'] ?? '' }}</strong><br>
                    C.C. No. {{ $e['representante_documento'] ?? '' }}{{ ! empty($e['representante_expedicion']) ? ' de '.$e['representante_expedicion'] : '' }}<br>
                    Representante legal · EL CONTRATISTA
                </div>
            </td>
            <td>
                @if ($firmaContratante)
                    <img class="firma-img" src="{{ $firmaContratante }}" alt="">
                @else
                    <div class="firma-hueco"></div>
                @endif
                <div class="linea">
                    <strong>{{ $doc?->firmante_nombre ?: ($v['contratante.nombre'] ?? '') }}</strong><br>
                    {{ $doc?->firmante_documento ? 'Documento No. '.$doc->firmante_documento : ($v['contratante.identificacion'] ?? '') }}<br>
                    EL CONTRATANTE
                    @if ($doc?->firmado())
                        <br><span style="font-size: 7.6px">Firmado electrónicamente el {{ $doc->firmado_at->format('d/m/Y h:i:s a') }}</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>
</main>

@if ($doc?->firmado())
    <section class="certificado">
        <h2>CERTIFICADO DE FIRMA ELECTRÓNICA</h2>
        <div class="sub">Contrato de prestación de servicios No. {{ $v['contrato.numero'] ?? '' }} · {{ $e['razon_social'] ?? '' }}</div>

        <table class="ev">
            <tr><th>Contratante</th><td>{{ $v['contratante.nombre'] ?? '' }} — {{ $v['contratante.identificacion'] ?? '' }}</td></tr>
            <tr><th>Firmante</th><td>{{ $doc->firmante_nombre }}{{ $doc->firmante_documento ? ' — documento No. '.$doc->firmante_documento : '' }}</td></tr>
            <tr><th>Verificación de identidad</th><td>Código de un solo uso enviado por {{ $doc->firma_canal_codigo === 'sms' ? 'mensaje de texto (SMS)' : 'correo electrónico' }} a {{ $doc->firma_destino_codigo }}</td></tr>
            <tr><th>Fecha y hora de la firma</th><td>{{ $doc->firmado_at->format('d/m/Y h:i:s a') }} (hora de Colombia, UTC-5)</td></tr>
            <tr><th>Dirección IP</th><td>{{ $doc->firma_ip }}</td></tr>
            <tr><th>Dispositivo</th><td>{{ \Illuminate\Support\Str::limit($doc->firma_user_agent, 180) }}</td></tr>
            <tr><th>Plantilla</th><td>{{ $e['codigo_formato'] ? $e['codigo_formato'].' · ' : '' }}versión {{ $e['version_formato'] ?? '' }}</td></tr>
            <tr><th>Huella del contenido (SHA-256)</th><td class="mono">{{ $doc->huella_contenido }}</td></tr>
            <tr><th>Código de verificación</th><td>
                <table><tr>
                    <td style="border: none; padding: 0" class="mono">{{ $doc->codigo_verificacion }}<br><br>{{ $doc->urlVerificacion() }}</td>
                    <td style="border: none; padding: 0; width: 80px; text-align: right"><img src="{{ $qr }}" style="width: 74px; height: 74px" alt="QR"></td>
                </tr></table>
            </td></tr>
        </table>

        <p style="font-weight: bold; margin: 12px 0 4px">Registro de eventos</p>
        <table class="ev">
            <tr><th style="width: 22%">Fecha y hora</th><th style="width: 30%">Evento</th><th style="width: 18%">IP</th><th>Detalle</th></tr>
            @foreach ($doc->eventos as $evento)
                <tr>
                    <td>{{ $evento->created_at?->format('d/m/Y h:i:s a') }}</td>
                    <td>{{ $evento->nombre }}</td>
                    <td>{{ $evento->ip ?: '—' }}</td>
                    <td>{{ collect($evento->detalle ?? [])->map(fn ($valor, $clave) => is_array($valor) ? $clave.': '.implode(', ', $valor) : $clave.': '.$valor)->implode(' · ') ?: '—' }}</td>
                </tr>
            @endforeach
        </table>

        <div class="legal">
            Firma electrónica realizada conforme a la Ley 527 de 1999 y al Decreto 2364 de 2012 (compilado en el Decreto 1074 de 2015).
            El firmante se identificó con un código de un solo uso enviado al medio de contacto indicado, revisó el documento y manifestó
            expresamente su aceptación. La huella del contenido permite comprobar que el texto no cambió después de emitido. La huella del
            archivo PDF firmado queda registrada en el sistema y puede comprobarse escaneando el código QR o en la dirección indicada.
        </div>
    </section>
@endif
</body>
</html>
