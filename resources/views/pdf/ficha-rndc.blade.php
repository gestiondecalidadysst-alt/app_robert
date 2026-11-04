{{-- Fichas para registrar terceros y vehículos en el RNDC. La arma App\Support\Pdf\PdfFichaRndc. --}}
@php $etiquetas = \App\Support\Pdf\PdfFichaRndc::VARIABLES; @endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ficha de registro RNDC</title>
<style>
    @page { margin: 34px 42px 40px 42px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; line-height: 1.4; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .logo { max-height: 50px; max-width: 130px; }
    h1 { font-size: 12px; margin: 0; }
    h2 { font-size: 10.5px; margin: 0 0 6px; padding: 4px 7px; background: #1B2B3A; color: #fff; }
    .tenue { color: #555; }
    .vars th, .vars td { border: 1px solid #c9d1da; padding: 3px 6px; }
    .vars th { background: #eef1f5; text-align: left; font-size: 8px; }
    .cod { font-family: DejaVu Sans Mono, monospace; font-size: 8px; color: #333; }
    .val { font-weight: bold; }
    .estado { padding: 5px 8px; margin: 6px 0; border: 1.5px solid; font-weight: bold; }
    .ok { border-color: #15803d; color: #14532d; background: #f0fdf4; }
    .mal { border-color: #b91c1c; color: #7f1d1d; background: #fef2f2; }
    .neutro { border-color: #475569; color: #1e293b; background: #f1f5f9; }
    .ficha { page-break-inside: avoid; margin-bottom: 16px; }
    .salto { page-break-after: always; }
    .nota { margin-top: 10px; font-size: 7.6px; color: #555; border-top: 1px solid #c9d1da; padding-top: 5px; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 22%">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
        <td style="padding-left: 6px">
            <div style="font-weight: bold; font-size: 10.5px">{{ mb_strtoupper($empresa->razon_social) }}</div>
            <div class="tenue">NIT {{ $empresa->nit }}{{ $empresa->dv !== null ? '-' . $empresa->dv : '' }}</div>
        </td>
        <td style="width: 38%; text-align: right">
            <h1>FICHA DE REGISTRO EN EL RNDC</h1>
            <div class="tenue">Registro Nacional de Despachos de Carga · Ministerio de Transporte</div>
            <div class="tenue">Generada el {{ now()->format('d/m/Y h:i a') }}</div>
        </td>
    </tr>
</table>
<div style="height: 10px"></div>

@foreach ($fichas as $i => $f)
    <div class="ficha">
        <h2>{{ $f['proceso'] === 11 ? 'TERCERO' : 'VEHÍCULO' }} · {{ $f['titulo'] }}</h2>
        @if ($f['ultimo'] && $f['estado'] !== 'error')
            <div class="estado {{ $f['estado'] === 'registrado' ? 'ok' : 'neutro' }}">
                Registrado en el RNDC · radicado No. {{ $f['ultimo']->ingresoid }} · {{ $f['ultimo']->created_at->timezone(config('app.timezone'))->format('d/m/Y h:i a') }}
                {{ $f['ultimo']->estado === 'manual' ? '(registrado en la web del RNDC)' : '' }}
                {{ $f['estado'] === 'desactualizado' ? '· Los datos cambiaron después: volver a registrar.' : '' }}
            </div>
        @elseif ($f['pendientes'])
            <div class="estado mal">
                Corrija antes de registrar:
                <ul style="margin: 3px 0 0 14px; padding: 0; font-weight: normal">@foreach ($f['pendientes'] as $p)<li>{{ $p }}</li>@endforeach</ul>
            </div>
        @else
            <div class="estado neutro">Listo para registrar en el RNDC (proceso {{ $f['proceso'] }}).</div>
        @endif
        <table class="vars">
            <tr><th style="width: 38%">Dato</th><th style="width: 30%">Variable del RNDC</th><th>Valor</th></tr>
            @foreach ($f['variables'] as $variable => $valor)
                <tr><td>{{ $etiquetas[$variable] ?? $variable }}</td><td class="cod">{{ $variable }}</td><td class="val">{{ $valor }}</td></tr>
            @endforeach
        </table>
    </div>
    @if (! $loop->last && $f['proceso'] !== ($fichas[$i + 1]['proceso'] ?? null))<div class="salto"></div>@endif
@endforeach

<div class="nota">
    {{ $conectado ? 'La empresa tiene conexión con el RNDC por web service: el registro se hace desde el sistema.' : 'La empresa aún no tiene conexión con el RNDC por web service. Registre estos datos en la web del RNDC (rndc.mintransporte.gov.co) y anote en el sistema el número de radicado que entrega el RNDC.' }}
    Variables y validaciones según el Manual RNDC Web-Service v2.3 del Ministerio de Transporte. Los códigos de marca, línea, color, carrocería y aseguradora son los de las tablas del Anexo a los Manuales RNDC.
</div>
</body>
</html>
