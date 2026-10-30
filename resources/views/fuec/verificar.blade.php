@php
    $estado = $fuec->estado();
    $colores = ['vigente' => '#15803d', 'programado' => '#1d4ed8', 'vencido' => '#475569', 'anulado' => '#b91c1c'];
    $color = $colores[$estado->value] ?? '#111';
    $ocultar = fn (?string $doc) => $doc ? str_repeat('•', max(0, strlen($doc) - 4)) . substr($doc, -4) : '—';
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Verificación FUEC</title>
<style>
    :root { color-scheme: light; }
    body { margin: 0; font-family: Inter, system-ui, -apple-system, Segoe UI, sans-serif; background: #F3F5F8; color: #111827; }
    .tarjeta { max-width: 520px; margin: 24px auto; background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden; }
    .cabecera { background: #1B2B3A; color: #fff; padding: 18px 20px; }
    .cabecera small { color: #9fb0c2; text-transform: uppercase; letter-spacing: .08em; font-size: 11px; }
    .estado { margin: 18px 20px 4px; display: inline-block; padding: 6px 14px; border-radius: 999px; color: #fff; font-weight: 700; background: {{ $color }}; }
    dl { margin: 12px 20px 20px; display: grid; grid-template-columns: 1fr; gap: 10px; }
    dt { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }
    dd { margin: 2px 0 0; font-size: 15px; }
    .placa { font-family: "JetBrains Mono", ui-monospace, monospace; font-weight: 700; background: #FFD23F; border: 2px solid #111; border-radius: 6px; padding: 1px 8px; }
    .num { font-family: ui-monospace, monospace; word-break: break-all; }
    .pie { padding: 12px 20px 18px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
    @media (max-width: 560px) { .tarjeta { margin: 0; border-radius: 0; } }
</style>
</head>
<body>
<div class="tarjeta">
    <div class="cabecera">
        <small>Verificación de FUEC</small>
        <div style="font-size: 18px; font-weight: 700; margin-top: 4px">{{ $d['empresa']['razon_social'] }}</div>
        <div style="color: #cbd5e1; font-size: 13px">NIT {{ $d['empresa']['nit'] }}</div>
    </div>

    <span class="estado">{{ $estado->getLabel() }}</span>

    <dl>
        <div><dt>FUEC No.</dt><dd class="num">{{ $fuec->numero }}</dd></div>
        <div><dt>Vehículo</dt><dd><span class="placa">{{ $d['vehiculo']['placa'] }}</span> {{ trim(($d['vehiculo']['marca'] ?? '').' '.($d['vehiculo']['linea'] ?? '')) }} {{ $d['vehiculo']['modelo'] }}</dd></div>
        <div><dt>Vigencia</dt><dd>{{ $fuec->fecha_inicio->format('d/m/Y') }} al {{ $fuec->fecha_fin->format('d/m/Y') }}</dd></div>
        <div><dt>Origen – destino</dt><dd>{{ $d['origen'] }} – {{ $d['destino'] }}</dd></div>
        <div><dt>Contrato</dt><dd>No. {{ $d['contrato']['numero'] }} · {{ $d['contrato']['contratante'] }}</dd></div>
        <div><dt>Conductores</dt><dd>
            @foreach ($d['conductores'] as $c)
                {{ $c['nombre'] }} ({{ $c['tipo_documento'] }} {{ $ocultar($c['documento']) }})@if (! $loop->last)<br>@endif
            @endforeach
        </dd></div>
        @if ($fuec->anulado())
            <div><dt>Anulado</dt><dd style="color: #b91c1c">{{ $fuec->anulado_at->timezone('America/Bogota')->format('d/m/Y') }}</dd></div>
        @endif
    </dl>

    <div class="pie">Compare estos datos con el documento físico. Consultado el {{ now()->timezone('America/Bogota')->format('d/m/Y h:i a') }}.</div>
</div>
</body>
</html>
