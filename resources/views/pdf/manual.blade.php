<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 120px 42px 70px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }
    header { position: fixed; top: -90px; left: 0; right: 0; height: 70px; border-bottom: 2px solid #15312a; }
    header .t { font-size: 15px; font-weight: bold; color: #15312a; }
    header .s { font-size: 10px; color: #6b7280; }
    footer { position: fixed; bottom: -50px; left: 0; right: 0; height: 40px; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 9px; padding-top: 6px; }
    .grupo { page-break-before: always; }
    .grupo:first-of-type { page-break-before: avoid; }
    .grupo h2 { font-size: 16px; color: #15312a; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; margin: 0 0 10px; }
    .seccion { margin: 0 0 16px; }
    .seccion h3 { font-size: 13px; color: #111827; margin: 14px 0 4px; }
    .seccion h2, .seccion h3, .seccion h4 { color: #15312a; }
    .cuerpo :is(h2, h3) { font-size: 12px; margin: 10px 0 4px; }
    .cuerpo ul, .cuerpo ol { margin: 4px 0 8px 18px; padding: 0; }
    .cuerpo p { margin: 4px 0; }
    .cuerpo blockquote { margin: 6px 0; padding: 4px 10px; border-left: 3px solid #15312a; background: #f3f5f8; color: #374151; }
    .portada { text-align: center; padding: 60px 0; }
    .portada .big { font-size: 26px; font-weight: bold; color: #15312a; }
    .portada .sub { font-size: 12px; color: #6b7280; margin-top: 8px; }
</style>
</head>
<body>
<header>
    <div class="t">Manual de la plataforma</div>
    <div class="s">Guía de uso de los módulos · SolucionesApp</div>
</header>
<footer>Manual de la plataforma · Generado el {{ now()->format('d/m/Y') }}</footer>

<div class="portada">
    <div class="big">Manual de la plataforma</div>
    <div class="sub">Qué hace cada módulo y cómo se usa en el día a día</div>
    <div class="sub">Generado el {{ now()->format('d/m/Y') }}</div>
</div>

@foreach ($grupos as $grupo => $secciones)
    <div class="grupo">
        <h2>{{ $grupo }}</h2>
        @foreach ($secciones as $seccion)
            <div class="seccion">
                <h3>{{ $seccion->titulo }}</h3>
                <div class="cuerpo">{!! $seccion->contenido !!}</div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
