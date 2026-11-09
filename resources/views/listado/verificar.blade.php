<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Verificación del listado de pasajeros</title>
<style>
    :root{--tinta:#15312a;--borde:#e4e8e4;--gris:#6b7772;}
    *{box-sizing:border-box;}
    body{margin:0;background:#eef1ee;color:#16201c;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;line-height:1.45;}
    .wrap{max-width:480px;margin:0 auto;padding:18px 16px 40px;}
    .card{background:#fff;border:1px solid var(--borde);border-radius:14px;overflow:hidden;}
    .top{background:var(--tinta);color:#fff;padding:16px 18px;}
    .top .ok{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.14);color:#d6f5e0;font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px;}
    .top h1{margin:10px 0 0;font-size:17px;}
    .top .emp{font-size:12px;color:#aec6ba;margin-top:2px;}
    .meta{padding:14px 18px;border-bottom:1px solid var(--borde);}
    .meta div{display:flex;justify-content:space-between;gap:12px;font-size:13.5px;padding:3px 0;}
    .meta .k{color:var(--gris);} .meta .v{font-weight:600;text-align:right;}
    .estado{font-weight:700;}
    .estado.cer{color:#166534;} .estado.ab{color:#92400e;}
    .lista .h{padding:11px 18px 6px;font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--gris);font-weight:700;}
    .row{display:flex;gap:10px;align-items:center;padding:10px 18px;border-top:1px solid #f0f2f0;}
    .n{flex:0 0 24px;height:24px;border-radius:50%;background:#eef2ff;color:#3730a3;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;}
    .nom{flex:1;min-width:0;} .nom b{font-weight:700;} .nom small{display:block;color:var(--gris);font-size:12.5px;}
    .foot{padding:14px 18px;color:var(--gris);font-size:11.5px;text-align:center;}
</style>
</head>
<body>
@php
    $mask = function (?string $doc): string {
        if ($doc === null || strlen($doc) <= 3) {
            return (string) $doc;
        }

        return str_repeat('•', strlen($doc) - 3).substr($doc, -3);
    };

    $vigencia = $fuec->fecha_inicio?->format('d/m/Y');
    if ($fuec->fecha_fin && ! $fuec->fecha_fin->isSameDay($fuec->fecha_inicio)) {
        $vigencia .= ' – '.$fuec->fecha_fin->format('d/m/Y');
    }
@endphp
<div class="wrap">
    <div class="card">
        <div class="top">
            @if ($anulado)
                <span class="ok" style="background:rgba(185,28,28,.25);color:#fecaca">&#9888; Extracto ANULADO</span>
            @else
                <span class="ok">&#10003; Listado verificado</span>
            @endif
            <h1>Listado de pasajeros</h1>
            @if ($empresa)
                <div class="emp">{{ $empresa }}</div>
            @endif
        </div>

        @if ($anulado)
            <div style="background:#fef2f2;color:#991b1b;padding:10px 18px;font-size:13px;font-weight:600;border-bottom:1px solid #fecaca">Este extracto (FUEC) fue anulado por la empresa. El listado no es válido.</div>
        @endif

        <div class="meta">
            <div><span class="k">FUEC No.</span><span class="v">{{ $fuec->numero }}</span></div>
            @if ($ruta)
                <div><span class="k">Recorrido</span><span class="v">{{ $ruta }}</span></div>
            @endif
            <div><span class="k">Vigencia</span><span class="v">{{ $vigencia }}</span></div>
            <div><span class="k">Estado</span><span class="v estado {{ $fuec->listadoCerrado() ? 'cer' : 'ab' }}">{{ $fuec->listadoCerrado() ? 'Cerrado' : 'Abierto' }}</span></div>
            <div><span class="k">Pasajeros</span><span class="v">{{ $fuec->pasajeros->count() }}</span></div>
        </div>

        <div class="lista">
            <div class="h">Pasajeros registrados</div>
            @if ($fuec->pasajeros->isEmpty())
                <div class="row"><div class="nom"><small>Aún no hay pasajeros registrados.</small></div></div>
            @endif
            @foreach ($fuec->pasajeros as $i => $p)
                <div class="row">
                    <div class="n">{{ $i + 1 }}</div>
                    <div class="nom">
                        <b>{{ $p->nombre }}</b>
                        <small>{{ $p->tipo_documento }} {{ $mask($p->documento) }}{{ $p->es_menor ? ' · Menor' : '' }}</small>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="foot">Los números de documento se muestran parcialmente por privacidad. Verificación en línea del listado de pasajeros del extracto (FUEC).</div>
    </div>
</div>
</body>
</html>
